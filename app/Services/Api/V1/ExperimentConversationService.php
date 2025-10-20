<?php

namespace App\Services\Api\V1;

use App\Models\ExperimentConversation;
use App\Models\ExperimentConversationMessage;
use App\Models\DatasetEmbedding;
use App\Models\User;
use App\Services\Api\V1\AI\RAGQueryService;
use Exception;
use Illuminate\Support\Facades\Log;

class ExperimentConversationService
{
    private RAGQueryService $ragService;

    public function __construct(RAGQueryService $ragService)
    {
        $this->ragService = $ragService;
    }

    /**
     * Get or create conversation for user, dataset embedding, and variant.
     */
    public function getOrCreateConversation(User $user, DatasetEmbedding $datasetEmbedding, string $variant): ExperimentConversation
    {
        return ExperimentConversation::findOrCreateForUser($user->id, $datasetEmbedding->id, $variant);
    }

    /**
     * Get conversation history.
     */
    public function getConversationHistory(User $user, DatasetEmbedding $datasetEmbedding, string $variant): ?array
    {
        $conversation = ExperimentConversation::where('user_id', $user->id)
            ->where('dataset_embedding_id', $datasetEmbedding->id)
            ->where('variant', $variant)
            ->first();

        if (!$conversation) {
            return null;
        }

        return [
            'conversation_id' => $conversation->id,
            'variant' => $variant,
            'created_at' => $conversation->created_at->toISOString(),
            'updated_at' => $conversation->updated_at->toISOString(),
            'total_messages' => $conversation->total_messages,
            'total_tokens' => $conversation->total_tokens,
            'should_suggest_new_conversation' => $conversation->shouldSuggestNewConversation(),
            'messages' => $conversation->getFullHistory(),
            'dataset_embedding' => [
                'id' => $datasetEmbedding->id,
                'uuid' => $datasetEmbedding->uuid,
                'variant' => $variant,
                'status' => $datasetEmbedding->status,
                'embedding_model' => $datasetEmbedding->embedding_model,
            ],
        ];
    }

    /**
     * Process a chat message and generate AI response.
     */
    public function processChatMessage(
        User $user,
        DatasetEmbedding $datasetEmbedding,
        string $message,
        string $variant,
        string $aiProvider = 'openai',
        array $options = []
    ): array {
        // Validate that embeddings are ready
        if (!$datasetEmbedding->isCompleted()) {
            throw new Exception('Dataset embeddings are not available or not completed. Please generate embeddings first.');
        }

        Log::info("Processing experiment conversation message", [
            'user_id' => $user->id,
            'dataset_embedding_id' => $datasetEmbedding->id,
            'variant' => $variant,
            'message_length' => strlen($message),
            'ai_provider' => $aiProvider
        ]);

        try {
            // Get or create conversation
            $conversation = $this->getOrCreateConversation($user, $datasetEmbedding, $variant);

            // Store user message
            $userMessage = ExperimentConversationMessage::createUserMessage($conversation->id, $message);

            // Get conversation context for AI
            $conversationContext = $conversation->getContextForAI();

            // Build enhanced query with conversation context
            $enhancedQuery = $this->buildEnhancedQuery($message, $conversationContext);

            // Perform RAG query using the dataset with the specific variant
            $ragResult = $this->ragService->queryDataset(
                $datasetEmbedding->dataset,
                $enhancedQuery,
                $aiProvider,
                $options,
                $variant
            );

            // Extract AI response and metadata
            $aiResponse = $ragResult['ai_response'];
            $contextChunks = $ragResult['context_chunks'] ?? [];
            $ragMetadata = [
                'relevant_chunks' => $ragResult['relevant_chunks'], // This is the count (integer)
                'search_query' => $enhancedQuery,
                'original_query' => $message,
                'context_chunks' => $contextChunks,
                'usage' => $ragResult['usage'] ?? null,
                'search_metadata' => $ragResult['search_metadata'] ?? null,
                'ai_model' => $ragResult['ai_model'] ?? null,
                'variant' => $variant,
            ];

            // Handle AI response and prepare content for storage
            $originalContent = null;
            $summarizedContent = null;
            
            if ($aiResponse !== null) {
                // Normal AI response - store and summarize
                $originalContent = is_array($aiResponse) ? json_encode($aiResponse) : $aiResponse;
                $summarizedContent = ExperimentConversationMessage::summarizeContent($originalContent, 300);
            } else {
                // Blocked by guardrails - use the error message from RAG service
                $errorMessage = $ragResult['message'] ?? 'Query blocked by content guardrails';
                $originalContent = null;
                $summarizedContent = $errorMessage;
            }

            // Store assistant message
            $assistantMessage = ExperimentConversationMessage::createAssistantMessage(
                $conversation->id,
                $originalContent,
                $summarizedContent,
                $ragMetadata
            );

            // Refresh conversation to get updated stats
            $conversation->refresh();

            Log::info("Experiment conversation message processed successfully", [
                'user_message_id' => $userMessage->id,
                'assistant_message_id' => $assistantMessage->id,
                'variant' => $variant,
                'total_tokens' => $conversation->total_tokens,
                'total_messages' => $conversation->total_messages
            ]);

            return [
                'conversation_id' => $conversation->id,
                'variant' => $variant,
                'message' => [
                    'id' => $assistantMessage->id,
                    'role' => 'assistant',
                    'content' => $assistantMessage->original_content,
                    'timestamp' => $assistantMessage->created_at->toISOString(),
                    'rag_metadata' => $assistantMessage->rag_metadata,
                    'token_count' => $assistantMessage->token_count,
                ],
                'conversation_stats' => [
                    'total_messages' => $conversation->total_messages,
                    'total_tokens' => $conversation->total_tokens,
                    'should_suggest_new_conversation' => $conversation->shouldSuggestNewConversation(),
                ],
                // Include original RAG response fields for compatibility
                'answer' => $aiResponse,
                'ai_response' => $aiResponse,
                'ai_response_format' => $ragResult['ai_response_format'] ?? 'text',
                'context_found' => $ragResult['context_found'],
                'relevant_chunks' => $ragResult['relevant_chunks'], // This is the count (integer)
                'citations' => $this->formatCitations($contextChunks), // Use the actual chunks array
                'usage' => $ragResult['usage'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error("Experiment conversation message processing failed", [
                'user_id' => $user->id,
                'dataset_embedding_id' => $datasetEmbedding->id,
                'variant' => $variant,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Build enhanced query with conversation context.
     */
    private function buildEnhancedQuery(string $message, array $conversationContext): string
    {
        if (empty($conversationContext)) {
            return $message;
        }

        // Simple context enhancement - prepend recent context
        $recentContext = array_slice($conversationContext, -3);
        $contextStr = implode("\n", array_map(function ($msg) {
            return "{$msg['role']}: {$msg['content']}";
        }, $recentContext));

        return "{$contextStr}\nuser: {$message}";
    }

    /**
     * Format citations from context chunks.
     */
    private function formatCitations(array $contextChunks): array
    {
        return array_map(function ($chunk) {
            return [
                'chunkId' => $chunk['chunk_id'] ?? $chunk['id'] ?? null,
                'score' => $chunk['score'] ?? 0,
                'text' => $chunk['text'] ?? '',
                'index' => $chunk['index'] ?? null,
            ];
        }, $contextChunks);
    }
}

