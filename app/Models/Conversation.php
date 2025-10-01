<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'dataset_embedding_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the conversation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the dataset embedding for this conversation.
     */
    public function datasetEmbedding(): BelongsTo
    {
        return $this->belongsTo(DatasetEmbedding::class);
    }

    /**
     * Get all messages for this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    /**
     * Get the latest message in the conversation.
     */
    public function latestMessage(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->latest();
    }

    /**
     * Get total estimated token count for the conversation.
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->messages()->sum('token_count') ?? 0;
    }

    /**
     * Get total message count.
     */
    public function getTotalMessagesAttribute(): int
    {
        return $this->messages()->count();
    }

    /**
     * Check if conversation is getting too long (suggest new conversation).
     */
    public function shouldSuggestNewConversation(int $tokenLimit = 8000): bool
    {
        return $this->total_tokens > $tokenLimit;
    }

    /**
     * Get conversation context for AI (summarized messages).
     */
    public function getContextForAI(): array
    {
        return $this->messages()
            ->select(['role', 'content', 'created_at'])
            ->get()
            ->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content, // This is the summarized content
                    'timestamp' => $message->created_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get full conversation history with all metadata.
     */
    public function getFullHistory(): array
    {
        return $this->messages()
            ->with([])
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->original_content ?? $message->content,
                    'summarized_content' => $message->content,
                    'rag_metadata' => $message->rag_metadata,
                    'token_count' => $message->token_count,
                    'created_at' => $message->created_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Find or create conversation for user and dataset embedding.
     */
    public static function findOrCreateForUser(int $userId, int $datasetEmbeddingId): self
    {
        // First try to find existing active conversation (excluding soft deleted)
        $conversation = static::where('user_id', $userId)
            ->where('dataset_embedding_id', $datasetEmbeddingId)
            ->first();
            
        if ($conversation) {
            return $conversation;
        }
        
        // Check if there's a soft deleted conversation that we need to restore
        $softDeletedConversation = static::withTrashed()
            ->where('user_id', $userId)
            ->where('dataset_embedding_id', $datasetEmbeddingId)
            ->whereNotNull('deleted_at')
            ->first();
            
        if ($softDeletedConversation) {
            // Restore the soft deleted conversation and clear its messages
            $softDeletedConversation->restore();
            // Clear all messages from the restored conversation to start fresh
            $softDeletedConversation->messages()->delete();
            return $softDeletedConversation;
        }
        
        // Create new conversation if none exists
        try {
            $conversation = static::create([
                'user_id' => $userId,
                'dataset_embedding_id' => $datasetEmbeddingId,
            ]);
            
            if (!$conversation) {
                throw new \Exception("Failed to create conversation - create() returned null");
            }
            
            return $conversation;
        } catch (\Exception $e) {
            // If creation fails due to unique constraint (race condition), try to find it again
            $existingConversation = static::where('user_id', $userId)
                ->where('dataset_embedding_id', $datasetEmbeddingId)
                ->first();
                
            if ($existingConversation) {
                return $existingConversation;
            }
            
            // If still no conversation found, re-throw the original exception
            throw new \Exception("Failed to create conversation for user {$userId} and dataset embedding {$datasetEmbeddingId}: " . $e->getMessage());
        }
    }

    /**
     * Clear conversation (soft delete) and create a new one.
     */
    public function clearAndCreateNew(): self
    {
        // Soft delete current conversation
        $this->delete();

        // Since we have a unique constraint, we need to restore this conversation
        // instead of creating a new one to avoid constraint violations
        $this->restore();
        
        // Clear all messages from this conversation
        $this->messages()->delete();
        
        return $this;
    }
}
