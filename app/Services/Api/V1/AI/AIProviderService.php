<?php

namespace App\Services\Api\V1\AI;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIProviderService
{
    private array $providers;

    public function __construct()
    {
        $this->providers = [
            'openai' => [
                'api_key' => config('services.openai.api_key'),
                'base_url' => config('services.openai.base_url', 'https://api.openai.com/v1'),
                'default_model' => config('services.openai.chat_model', 'gpt-4'),
                'max_tokens' => config('services.openai.max_tokens', 4000),
            ],
            'claude' => [
                'api_key' => config('services.claude.api_key'),
                'base_url' => config('services.claude.base_url', 'https://api.anthropic.com/v1'),
                'default_model' => config('services.claude.model', 'claude-3-sonnet-20240229'),
                'max_tokens' => config('services.claude.max_tokens', 4000),
            ]
        ];
    }

    /**
     * Generate AI response using the specified provider.
     */
    public function generateResponse(
        string $provider, 
        string $prompt, 
        array $options = []
    ): array {
        $this->validateProvider($provider);
        
        $config = $this->providers[$provider];
        $model = $options['model'] ?? $config['default_model'];
        $maxTokens = $options['max_tokens'] ?? $config['max_tokens'];
        $temperature = $options['temperature'] ?? 0.7;

        Log::info("Generating AI response", [
            'provider' => $provider,
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'max_tokens' => $maxTokens
        ]);

        try {
            switch ($provider) {
                case 'openai':
                    return $this->callOpenAI($prompt, $model, $maxTokens, $temperature);
                case 'claude':
                    return $this->callClaude($prompt, $model, $maxTokens, $temperature);
                default:
                    throw new Exception("Unsupported AI provider: {$provider}");
            }
        } catch (Exception $e) {
            Log::error("AI provider request failed", [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Call OpenAI ChatGPT API.
     */
    private function callOpenAI(string $prompt, string $model, int $maxTokens, float $temperature): array
    {
        $config = $this->providers['openai'];
        
        if (!$config['api_key']) {
            throw new Exception('OpenAI API key is not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json'
        ])->timeout(120)->post($config['base_url'] . '/chat/completions', [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'stream' => false
        ]);

        if (!$response->successful()) {
            throw new Exception('OpenAI API error: ' . $response->status() . ' - ' . $response->body());
        }

        $data = $response->json();
        
        return [
            'provider' => 'openai',
            'model' => $model,
            'response' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
            ],
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'unknown',
            'raw_response' => $data
        ];
    }

    /**
     * Call Claude API.
     */
    private function callClaude(string $prompt, string $model, int $maxTokens, float $temperature): array
    {
        $config = $this->providers['claude'];
        
        if (!$config['api_key']) {
            throw new Exception('Claude API key is not configured');
        }

        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01'
        ])->timeout(120)->post($config['base_url'] . '/messages', [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ]);

        if (!$response->successful()) {
            throw new Exception('Claude API error: ' . $response->status() . ' - ' . $response->body());
        }

        $data = $response->json();
        
        return [
            'provider' => 'claude',
            'model' => $model,
            'response' => $data['content'][0]['text'] ?? '',
            'usage' => [
                'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
            'stop_reason' => $data['stop_reason'] ?? 'unknown',
            'raw_response' => $data
        ];
    }

    /**
     * Validate provider is supported.
     */
    private function validateProvider(string $provider): void
    {
        if (!array_key_exists($provider, $this->providers)) {
            throw new Exception("Unsupported AI provider: {$provider}. Supported providers: " . implode(', ', array_keys($this->providers)));
        }
    }

    /**
     * Check if a provider is configured and available.
     */
    public function isProviderAvailable(string $provider): bool
    {
        return isset($this->providers[$provider]) && !empty($this->providers[$provider]['api_key']);
    }
}

