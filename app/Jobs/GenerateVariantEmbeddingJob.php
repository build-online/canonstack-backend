<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use App\Services\Api\V1\Embeddings\EmbeddingPipelineFactory;
use Exception;
use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateVariantEmbeddingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 hour timeout (for large datasets)
    public $tries = 1; // Don't retry failed jobs automatically
    public $maxExceptions = 1;
    public $failOnTimeout = false; // Don't fail the job on timeout, let it complete
    public $uniqueFor = 7200; // Keep the unique lock for 2 hours (same as timeout)

    private Dataset $dataset;
    private string $variant;
    private array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(Dataset $dataset, string $variant, array $options = [])
    {
        $this->dataset = $dataset;
        $this->variant = $variant;
        $this->options = $options;
        
        // Use a specific queue for embedding jobs
        $this->onQueue('embeddings');
        
        Log::info("Variant embedding job queued", [
            'dataset_id' => $dataset->id,
            'dataset_uuid' => $dataset->uuid,
            'variant' => $variant,
            'options' => $options
        ]);
    }

    /**
     * Get the unique ID for the job.
     * Ensures only one job per dataset/variant combination can be queued at a time.
     */
    public function uniqueId(): string
    {
        return "generate-variant-embedding-{$this->dataset->id}-{$this->variant}";
    }

    /**
     * Execute the job.
     */
    public function handle(EmbeddingPipelineFactory $pipelineFactory): void
    {
        try {
            Log::info("Starting variant embedding generation job", [
                'dataset_id' => $this->dataset->id,
                'dataset_uuid' => $this->dataset->uuid,
                'variant' => $this->variant,
                'job_id' => $this->job->getJobId(),
                'attempts' => $this->attempts(),
                'max_tries' => $this->tries
            ]);

            // Get the embedding record for this variant
            $embedding = $this->dataset->getEmbeddingByVariant($this->variant);
            
            if (!$embedding) {
                throw new Exception("No embedding record found for variant: {$this->variant}");
            }

            // Get the appropriate pipeline for this variant
            $pipeline = $pipelineFactory->make($this->variant);
            
            // Generate embeddings using the pipeline
            $pipeline->process($this->dataset, $embedding, $this->options);

            Log::info("Variant embedding generation job completed successfully", [
                'dataset_id' => $this->dataset->id,
                'variant' => $this->variant,
                'embedding_id' => $embedding->id,
                'total_chunks' => $embedding->total_chunks,
                'total_points' => $embedding->total_points,
                'job_id' => $this->job->getJobId()
            ]);

        } catch (Throwable $e) {
            Log::error("Variant embedding generation job failed", [
                'dataset_id' => $this->dataset->id,
                'dataset_uuid' => $this->dataset->uuid,
                'variant' => $this->variant,
                'job_id' => $this->job->getJobId(),
                'attempts' => $this->attempts(),
                'max_tries' => $this->tries,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark the embedding as failed in the database
            $this->markEmbeddingAsFailed($e->getMessage());

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        // Only log if this is not a duplicate job rejection
        // (Duplicate jobs will have null job property and MaxAttemptsExceededException)
        $isDuplicateRejection = !$this->job && $exception instanceof \Illuminate\Queue\MaxAttemptsExceededException;
        
        if (!$isDuplicateRejection) {
            Log::error("Variant embedding generation job permanently failed", [
                'dataset_id' => $this->dataset->id,
                'dataset_uuid' => $this->dataset->uuid,
                'variant' => $this->variant,
                'job_id' => $this->job?->getJobId(),
                'attempts' => $this->job ? $this->attempts() : 'unknown',
                'max_tries' => $this->tries,
                'error_type' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_file' => $exception->getFile(),
                'error_line' => $exception->getLine()
            ]);

            $this->markEmbeddingAsFailed($exception->getMessage());
        }
    }

    /**
     * Mark the embedding as failed in the database.
     */
    private function markEmbeddingAsFailed(string $errorMessage): void
    {
        try {
            $embedding = $this->dataset->getEmbeddingByVariant($this->variant);
            if ($embedding) {
                $embedding->markAsFailed($errorMessage);
            }
        } catch (Throwable $e) {
            Log::error("Failed to mark variant embedding as failed", [
                'dataset_id' => $this->dataset->id,
                'variant' => $this->variant,
                'error' => $e->getMessage()
            ]);
        }
    }
}

