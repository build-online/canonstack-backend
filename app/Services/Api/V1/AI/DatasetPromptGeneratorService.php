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

        $systemPromptForAI = "You are an expert prompt engineer specializing in creating RAG (Retrieval-Augmented Generation) system prompts. Your task is to analyze the provided dataset sample and create a comprehensive system prompt that will help the RAG assistant provide accurate, relevant, and well-formatted responses by effectively leveraging all available metadata fields.";

        $userPrompt = <<<PROMPT
I need you to create a comprehensive system prompt for a RAG assistant that will be answering questions based on the following dataset:

## Dataset Name:
{$repositoryName}

## Repository Description:
{$repositoryDescription}

## Dataset Structure Description:
{$structureDescription}

---

Please create a system prompt based on the following rules:

**1. Expert Role Definition**: Define what kind of expert the AI should be based on the dataset content and domain.
**2. Core RAG Instructions** (MUST INCLUDE THESE EXACTLY):
   - "### CRITICAL INSTRUCTIONS:"
   - "You are a RAG (Retrieval-Augmented Generation) assistant. You must follow these rules:"
   - "1. Answer questions confidently using the provided context from the dataset"
   - "2. Make reasonable connections and interpretations based on the context provided"
   - "3. If the context contains relevant information, provide a comprehensive answer"
**3. Metadata-Driven Retrieval Guidelines**: Analyze the dataset's metadata fields and create 4-6 specific instructions that tell the AI how to:
   - **Prioritize high-value metadata matches**: Identify which metadata fields are most critical for accurate retrieval (e.g., exact IDs, categories, dates, hierarchical references) and instruct the system to heavily weight chunks containing exact matches in these fields
   - **Apply hierarchical filtering**: If the dataset has hierarchical or structured identifiers, create cascading priority rules (exact match > partial match > category match)
   - **Leverage categorical metadata**: Instruct how to use classification fields (types, categories, tags) to ensure comprehensive coverage within the queried scope before expanding to related areas
   - **Handle temporal or sequential data**: If applicable, provide guidance on chronological or sequential ordering using date, version, or sequence metadata
   - **Cross-reference related fields**: Explain how to use relational metadata fields to provide complete context and connections
   - **Maintain scope discipline**: Ensure the system exhaustively covers the primary query scope using metadata filters before including tangentially related content
**4. Domain-Specific Response Guidelines**: Based on the dataset content, specify:
   - How to structure responses using the available metadata
   - What citation format to use incorporating the metadata fields
   - Any domain-specific conventions or terminology to follow
   - How to organize information hierarchically based on the metadata structure
**5. Quality Control Instructions**: Add rules to ensure:
   - All relevant chunks matching the metadata criteria are included before expanding scope
   - Responses acknowledge the metadata-based organization of information
   - Citations properly reference the key identifying metadata fields
### LANGUAGE & TERMINOLOGY REQUIREMENTS (CRITICAL):
- **Use human-readable, common language throughout the system prompt** - avoid technical field names
- **Translate abbreviated field names into full, clear terms**:
  - Infer the meaning of technical field names and use natural language equivalents
- **Write instructions in plain language** that a domain expert would understand, not database column names
- **Use terminology natural to the dataset's domain**, not technical metadata field names
### OUTPUT REQUIREMENTS:
- Write as a complete, ready-to-use system prompt (not instructions about a prompt)
- Keep focused and professional
- Aim for 400-600 words
- Ensure the prompt will solve retrieval precision issues by making metadata utilization explicit and mandatory
- Include specific examples using natural, human-readable language (NOT technical field names)
- All references to metadata fields must be in common language that domain users would understand
Analyze the dataset sample carefully to identify the most important metadata fields, translate them to human-readable terms, then create a prompt that makes their strategic use mandatory for the RAG assistant using natural, accessible language.
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

