<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use App\Services\Api\V1\DatasetEmbeddingService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDatasetEmbeddings implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 1; // Don't retry failed jobs automatically
    public $maxExceptions = 1;
    public $uniqueFor = 3600; // Keep the unique lock for 1 hour (same as timeout)

    private Dataset $dataset;
    private array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(Dataset $dataset, array $options = [])
    {
        $this->dataset = $dataset;
        $this->options = $options;
        
        // Use a specific queue for embedding jobs
        $this->onQueue('embeddings');
        
        Log::info("Embedding job queued", [
            'dataset_id' => $dataset->id,
            'dataset_uuid' => $dataset->uuid,
            'options' => $options
        ]);
    }

    /**
     * Get the unique ID for the job.
     * Ensures only one job per dataset can be queued at a time.
     */
    public function uniqueId(): string
    {
        return "generate-dataset-embedding-{$this->dataset->id}";
    }

    /**
     * Execute the job.
     */
    public function handle(DatasetEmbeddingService $embeddingService): void
    {
        try {
            Log::info("Starting embedding generation job", [
                'dataset_id' => $this->dataset->id,
                'dataset_uuid' => $this->dataset->uuid,
                'job_id' => $this->job->getJobId(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ]);

            // Generate embeddings - this will handle all the heavy lifting
            $embedding = $embeddingService->generateEmbeddings($this->dataset, $this->options);

            Log::info("Embedding generation job completed successfully", [
                'dataset_id' => $this->dataset->id,
                'embedding_id' => $embedding->id,
                'total_chunks' => $embedding->total_chunks,
                'total_points' => $embedding->total_points,
                'job_id' => $this->job->getJobId()
            ]);

            // TODO: Send notification to user that embeddings are ready
            // $this->notifyUser($embedding);

        } catch (Exception $e) {
            Log::error("Embedding generation job failed", [
                'dataset_id' => $this->dataset->id,
                'dataset_uuid' => $this->dataset->uuid,
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
        // Only log if this is not a duplicate job rejection
        // (Duplicate jobs will have null job property and MaxAttemptsExceededException)
        $isDuplicateRejection = !$this->job && $exception instanceof \Illuminate\Queue\MaxAttemptsExceededException;
        
        if (!$isDuplicateRejection) {
            Log::error("Embedding generation job permanently failed", [
                'dataset_id' => $this->dataset->id,
                'dataset_uuid' => $this->dataset->uuid,
                'job_id' => $this->job?->getJobId(),
                'error' => $exception->getMessage()
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
            $embedding = DatasetEmbedding::where('dataset_id', $this->dataset->id)->first();
            if ($embedding) {
                $embedding->markAsFailed($errorMessage);
            }
        } catch (Exception $e) {
            Log::error("Failed to mark embedding as failed", [
                'dataset_id' => $this->dataset->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}