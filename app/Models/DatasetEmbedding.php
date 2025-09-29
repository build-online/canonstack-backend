<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasUuid;

class DatasetEmbedding extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'dataset_id',
        'qdrant_collection_name',
        'total_chunks',
        'total_points',
        'embedding_model',
        'chunk_size',
        'chunk_overlap',
        'status',
        'error_message',
        'processing_stats',
        'processing_started_at',
        'processing_completed_at',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'total_chunks' => 'integer',
        'total_points' => 'integer',
        'chunk_size' => 'integer',
        'chunk_overlap' => 'integer',
        'processing_stats' => 'array',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    /**
     * Get the dataset that owns this embedding.
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * Check if processing is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if processing is in progress.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if processing failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark as processing started.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'processing_started_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark as completed.
     */
    public function markAsCompleted(int $totalChunks, int $totalPoints, array $stats = []): void
    {
        $this->update([
            'status' => 'completed',
            'total_chunks' => $totalChunks,
            'total_points' => $totalPoints,
            'processing_completed_at' => now(),
            'processing_stats' => $stats,
            'error_message' => null,
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'processing_completed_at' => now(),
        ]);
    }
}