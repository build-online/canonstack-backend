<?php

namespace App\Services\Api\V1\AI;

use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class DatasetPromptGeneratorService
{
    /**
     * Generate a dataset-specific system prompt based on structure description.
     * 
     * @param Dataset $dataset
     * @param DatasetEmbedding $embedding
     * @param string $structureDescription
     * @return string The generated system prompt
     */
    public function generateSystemPrompt(
        Dataset $dataset,
        DatasetEmbedding $embedding,
        string $structureDescription
    ): string {
        try {
            $repositoryDescription = $dataset->repository->description ?? null;
            $repositoryName = $dataset->repository->name ?? 'this dataset';

            $systemPrompt = $this->generatePromptWithAI(
                $structureDescription,
                $repositoryDescription,
                $repositoryName
            );

            return $systemPrompt;

        } catch (Exception $e) {
            Log::error("System prompt generation failed", [
                'dataset_id' => $dataset->id,
                'embedding_id' => $embedding->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate system prompt using OpenAI.
     */
    private function generatePromptWithAI(
        string $structureDescription,
        ?string $repositoryDescription,
        string $repositoryName
    ): string {
        $apiKey = config('services.openai.api_key');
        
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }

        $systemPromptForAI = "You are an expert in creating system prompts for RAG (Retrieval-Augmented Generation) AI assistants. Your task is to create an effective system prompt that will guide an AI to answer questions accurately based on a specific dataset.";

        $userPrompt = <<<PROMPT
I need you to create a comprehensive system prompt for a RAG assistant that will be answering questions based on the following dataset:

## Dataset Name:
{$repositoryName}

## Repository Description:
{$repositoryDescription}

## Dataset Structure Description:
{$structureDescription}

---

Please create a system prompt that includes:

1. **Expert Role Definition**: Define what kind of expert the AI should be based on the dataset content (e.g., "You are an expert on biblical texts and cross-references", "You are an expert on medical research papers", etc.)

2. **Core RAG Instructions** (MUST INCLUDE THESE EXACTLY):
   - "### CRITICAL INSTRUCTIONS:"
   - "You are a RAG (Retrieval-Augmented Generation) assistant. You must follow these rules:"
   - "1. Answer questions confidently using the provided context from the dataset"
   - "2. Make reasonable connections and interpretations based on the context provided"
   - "3. If the context contains relevant information, provide a comprehensive answer"

3. **Dataset-Specific Instructions**: Based on the structure and content of this specific dataset, add 3-5 additional instructions that tell the AI:
   - How to use the metadata fields when answering questions
   - What kind of citations or references to provide
   - Any domain-specific conventions or patterns to follow
   - How to handle edge cases specific to this dataset type

4. **Response Guidelines**: Instructions on how to format responses, what tone to use, and how to cite sources using the available metadata.

Create a clear, well-structured system prompt that will help the AI provide accurate, relevant, and well-formatted responses. Write it as if it's the actual system prompt that will be used directly (not as instructions about a prompt).

Keep the prompt focused and professional. It should be comprehensive but concise (aim for 300-500 words).
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPromptForAI],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.4,
                'max_tokens' => 1500,
            ]);

            if (!$response->successful()) {
                throw new Exception("OpenAI API request failed: " . $response->body());
            }

            $result = $response->json();
            $generatedPrompt = $result['choices'][0]['message']['content'] ?? '';

            if (empty($generatedPrompt)) {
                throw new Exception("Empty prompt received from OpenAI");
            }

            return trim($generatedPrompt);

        } catch (Exception $e) {
            Log::error("Failed to generate system prompt with AI", [
                'error' => $e->getMessage()
            ]);
            throw new Exception("Failed to generate system prompt: " . $e->getMessage());
        }
    }
}

