<?php

namespace App\Services\Api\V1;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\DatasetEmbedding;
use App\Models\User;
use App\Services\Api\V1\AI\RAGQueryService;
use Exception;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    private RAGQueryService $ragService;

    public function __construct(RAGQueryService $ragService)
    {
        $this->ragService = $ragService;
    }

    /**
     * Get or create conversation for user and dataset embedding.
     */
    public function getOrCreateConversation(User $user, DatasetEmbedding $datasetEmbedding): Conversation
    {
        return Conversation::findOrCreateForUser($user->id, $datasetEmbedding->id);
    }

    /**
     * Get conversation with full history.
     */
    public function getConversationHistory(User $user, DatasetEmbedding $datasetEmbedding): ?array
    {
        $conversation = Conversation::where('user_id', $user->id)
            ->where('dataset_embedding_id', $datasetEmbedding->id)
            ->first();

        if (!$conversation) {
            return null;
        }

        return [
            'conversation_id' => $conversation->id,
            'created_at' => $conversation->created_at->toISOString(),
            'updated_at' => $conversation->updated_at->toISOString(),
            'total_messages' => $conversation->total_messages,
            'total_tokens' => $conversation->total_tokens,
            'should_suggest_new_conversation' => $conversation->shouldSuggestNewConversation(),
            'messages' => $conversation->getFullHistory(),
            'dataset_embedding' => [
                'id' => $datasetEmbedding->id,
                'uuid' => $datasetEmbedding->uuid,
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
        string $aiProvider = 'openai',
        array $options = []
    ): array {
        // Validate that embeddings are ready
        if (!$datasetEmbedding->isCompleted()) {
            throw new Exception('Dataset embeddings are not available or not completed. Please generate embeddings first.');
        }

        Log::info("Processing conversation message", [
            'user_id' => $user->id,
            'dataset_embedding_id' => $datasetEmbedding->id,
            'message_length' => strlen($message),
            'ai_provider' => $aiProvider
        ]);

        try {
            // Get or create conversation
            $conversation = $this->getOrCreateConversation($user, $datasetEmbedding);

            // Store user message
            $userMessage = ConversationMessage::createUserMessage($conversation->id, $message);

            // Get conversation context for AI
            $conversationContext = $conversation->getContextForAI();

            // Build enhanced query with conversation context
            $enhancedQuery = $this->buildEnhancedQuery($message, $conversationContext);

            // Perform RAG query
            $ragResult = $this->ragService->queryDataset(
                $datasetEmbedding->dataset,
                $enhancedQuery,
                $aiProvider,
                $options
            );

            // Extract AI response and metadata
            $aiResponse = $ragResult['ai_response'];
            $ragMetadata = [
                'search_performed' => true, // Search is always performed, even if results are filtered out
                'relevant_chunks' => $ragResult['relevant_chunks'],
                'search_query' => $enhancedQuery,
                'original_query' => $message,
                'context_chunks' => $ragResult['context_chunks'] ?? [],
                'usage' => $ragResult['usage'] ?? null,
                'search_metadata' => $ragResult['search_metadata'] ?? null,
                'ai_model' => $ragResult['ai_model'] ?? null,
            ];

            // Handle AI response and prepare content for storage
            $originalContent = null;
            $summarizedContent = null;
            
            if ($aiResponse !== null) {
                // Normal AI response - store and summarize
                $originalContent = is_array($aiResponse) ? json_encode($aiResponse) : $aiResponse;
                $summarizedContent = ConversationMessage::summarizeContent($originalContent, 300);
            } else {
                // Blocked by guardrails - use the error message from RAG service
                $errorMessage = $ragResult['message'] ?? 'Query blocked by content guardrails';
                $originalContent = null; // No AI response generated
                $summarizedContent = $errorMessage; // Use error message as content
            }

            // Store assistant message
            $assistantMessage = ConversationMessage::createAssistantMessage(
                $conversation->id,
                $originalContent,
                $summarizedContent,
                $ragMetadata
            );

            // Refresh conversation to get updated stats
            $conversation->refresh();

            Log::info("Conversation message processed successfully", [
                'conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'assistant_message_id' => $assistantMessage->id,
                'total_tokens' => $conversation->total_tokens,
                'total_messages' => $conversation->total_messages
            ]);

            return [
                'conversation_id' => $conversation->id,
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
                // Include original RAG response fields for backward compatibility
                'ai_response' => $aiResponse,
                'ai_response_format' => $ragResult['ai_response_format'] ?? 'text',
                'context_found' => $ragResult['context_found'],
                'relevant_chunks' => $ragResult['relevant_chunks'],
                'usage' => $ragResult['usage'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error("Conversation message processing failed", [
                'user_id' => $user->id,
                'dataset_embedding_id' => $datasetEmbedding->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new Exception("Failed to process conversation message: " . $e->getMessage());
        }
    }

    /**
     * Clear conversation (soft delete).
     */
    public function clearConversation(User $user, DatasetEmbedding $datasetEmbedding): bool
    {
        $conversation = Conversation::where('user_id', $user->id)
            ->where('dataset_embedding_id', $datasetEmbedding->id)
            ->first();

        if (!$conversation) {
            return false;
        }

        Log::info("Clearing conversation", [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'dataset_embedding_id' => $datasetEmbedding->id,
            'total_messages' => $conversation->total_messages
        ]);

        $conversation->delete();
        return true;
    }

    /**
     * Build enhanced query with conversation context.
     */
    private function buildEnhancedQuery(string $currentMessage, array $conversationContext): string
    {
        // If no conversation context, return original message
        if (empty($conversationContext)) {
            return $currentMessage;
        }

        // Get recent context (last 3 messages or so)
        $recentContext = array_slice($conversationContext, -6); // Last 3 exchanges

        // Build context summary
        $contextSummary = '';
        foreach ($recentContext as $msg) {
            if ($msg['role'] === 'user') {
                $contextSummary .= "User previously asked: " . $msg['content'] . "\n";
            } else {
                $contextSummary .= "Assistant responded: " . $msg['content'] . "\n";
            }
        }

        // Combine context with current message
        return "Previous conversation context:\n" . $contextSummary . "\nCurrent question: " . $currentMessage;
    }

    /**
     * Get conversation statistics.
     */
    public function getConversationStats(User $user, DatasetEmbedding $datasetEmbedding): array
    {
        $conversation = Conversation::where('user_id', $user->id)
            ->where('dataset_embedding_id', $datasetEmbedding->id)
            ->first();

        if (!$conversation) {
            return [
                'exists' => false,
                'total_messages' => 0,
                'total_tokens' => 0,
                'should_suggest_new_conversation' => false,
            ];
        }

        return [
            'exists' => true,
            'conversation_id' => $conversation->id,
            'total_messages' => $conversation->total_messages,
            'total_tokens' => $conversation->total_tokens,
            'should_suggest_new_conversation' => $conversation->shouldSuggestNewConversation(),
            'created_at' => $conversation->created_at->toISOString(),
            'updated_at' => $conversation->updated_at->toISOString(),
        ];
    }
}
