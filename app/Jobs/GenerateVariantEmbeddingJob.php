<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use App\Services\Api\V1\Embeddings\EmbeddingPipelineFactory;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateVariantEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 1; // Don't retry failed jobs automatically
    public $maxExceptions = 1;

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
     * Execute the job.
     */
    public function handle(EmbeddingPipelineFactory $pipelineFactory): void
    {
        try {
            Log::info("Starting variant embedding generation job", [
                'dataset_id' => $this->dataset->id,
                'dataset_uuid' => $this->dataset->uuid,
                'variant' => $this->variant,
                'job_id' => $this->job->getJobId()
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

        } catch (Exception $e) {
            Log::error("Variant embedding generation job failed", [
                'dataset_id' => $this->dataset->id,
                'dataset_uuid' => $this->dataset->uuid,
                'variant' => $this->variant,
                'job_id' => $this->job->getJobId(),
                'error' => $e->getMessage(),
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
    public function failed(Exception $exception): void
    {
        Log::error("Variant embedding generation job permanently failed", [
            'dataset_id' => $this->dataset->id,
            'dataset_uuid' => $this->dataset->uuid,
            'variant' => $this->variant,
            'job_id' => $this->job?->getJobId(),
            'error' => $exception->getMessage()
        ]);

        $this->markEmbeddingAsFailed($exception->getMessage());
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
        } catch (Exception $e) {
            Log::error("Failed to mark variant embedding as failed", [
                'dataset_id' => $this->dataset->id,
                'variant' => $this->variant,
                'error' => $e->getMessage()
            ]);
        }
    }
}

