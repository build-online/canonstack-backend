<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperimentConversationMessage extends Model
{
    protected $fillable = [
        'experiment_conversation_id',
        'role',
        'original_content',
        'summarized_content',
        'rag_metadata',
        'token_count',
    ];

    protected $casts = [
        'rag_metadata' => 'array',
        'token_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the conversation that owns this message.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ExperimentConversation::class, 'experiment_conversation_id');
    }

    /**
     * Check if this is a user message.
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if this is an assistant message.
     */
    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Get the display content (original if available, otherwise summarized).
     */
    public function getDisplayContentAttribute(): string
    {
        return $this->original_content ?? $this->summarized_content;
    }

    /**
     * Estimate token count from content.
     */
    public static function estimateTokenCount(?string $content): int
    {
        if ($content === null) {
            return 0;
        }
        
        // Simple approximation: 4 characters ≈ 1 token
        return (int) ceil(strlen($content) / 4);
    }

    /**
     * Create a user message.
     */
    public static function createUserMessage(string $conversationId, string $content): self
    {
        $tokenCount = static::estimateTokenCount($content);

        return static::create([
            'experiment_conversation_id' => $conversationId,
            'role' => 'user',
            'summarized_content' => $content,
            'original_content' => $content, // User messages don't need summarization
            'token_count' => $tokenCount,
        ]);
    }

    /**
     * Create an assistant message with RAG metadata.
     */
    public static function createAssistantMessage(
        string $conversationId,
        ?string $originalContent,
        string $summarizedContent,
        ?array $ragMetadata = null
    ): self {
        $tokenCount = $originalContent ? static::estimateTokenCount($originalContent) : 0;

        return static::create([
            'experiment_conversation_id' => $conversationId,
            'role' => 'assistant',
            'summarized_content' => $summarizedContent,
            'original_content' => $originalContent,
            'rag_metadata' => $ragMetadata,
            'token_count' => $tokenCount,
        ]);
    }

    /**
     * Summarize content for context (light summarization).
     */
    public static function summarizeContent(?string $content, int $maxLength = 200): ?string
    {
        if ($content === null) {
            return null;
        }
        
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        // Simple summarization: take first part + key information
        $firstPart = substr($content, 0, $maxLength * 0.7);
        $lastPart = substr($content, -($maxLength * 0.3));

        // Try to break at word boundaries
        $firstPart = substr($firstPart, 0, strrpos($firstPart, ' '));
        $lastPart = substr($lastPart, strpos($lastPart, ' '));

        return $firstPart . '...' . $lastPart;
    }
}

