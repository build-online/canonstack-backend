<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Models\DatasetEmbedding;
use App\Services\Api\V1\DatasetEmbeddingService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDatasetEmbeddings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 1; // Don't retry failed jobs automatically
    public $maxExceptions = 1;

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
        Log::error("Embedding generation job permanently failed", [
            'dataset_id' => $this->dataset->id,
            'dataset_uuid' => $this->dataset->uuid,
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

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'embeddings',
            'dataset:' . $this->dataset->id,
            'uuid:' . $this->dataset->uuid
        ];
    }
}