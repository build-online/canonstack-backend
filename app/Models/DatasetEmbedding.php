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
        'variant',
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
        'structure_description',
        'system_prompt',
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
        return $this->status === 'COMPLETED';
    }

    /**
     * Check if processing is in progress.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'PROCESSING';
    }

    /**
     * Check if processing failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    /**
     * Mark as processing started.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'PROCESSING',
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
            'status' => 'COMPLETED',
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
            'status' => 'FAILED',
            'error_message' => $errorMessage,
            'processing_completed_at' => now(),
        ]);
    }

    /**
     * Check if embedding is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    /**
     * Check if this is an experimental embedding (has a variant).
     */
    public function isExperimental(): bool
    {
        return $this->variant !== null;
    }




    /**
     * Get processing progress as percentage.
     */
    public function getProgressPercentage(): ?float
    {
        if ($this->isPending()) {
            return 0.0;
        }

        if ($this->isCompleted()) {
            return 100.0;
        }

        if ($this->hasFailed()) {
            return null; // No progress for failed jobs
        }

        if ($this->isProcessing()) {
            // For processing jobs, we can estimate progress based on chunks processed
            // This would need to be updated by the job itself for real progress tracking
            return null; // TODO: Implement real progress tracking
        }

        return null;
    }

    /**
     * Get human-readable status.
     */
    public function getStatusText(): string
    {
        return match($this->status) {
            'PENDING' => 'Queued for processing',
            'PROCESSING' => 'Currently processing',
            'COMPLETED' => 'Completed successfully',
            'FAILED' => 'Processing failed',
            default => 'Unknown status'
        };
    }

    /**
     * Get estimated time remaining (placeholder for future implementation).
     */
    public function getEstimatedTimeRemaining(): ?string
    {
        // TODO: Implement based on average processing times and current progress
        if ($this->isProcessing() && $this->processing_started_at) {
            $elapsed = $this->processing_started_at->diffInMinutes(now());
            return "Processing for {$elapsed} minutes";
        }

        return null;
    }
}