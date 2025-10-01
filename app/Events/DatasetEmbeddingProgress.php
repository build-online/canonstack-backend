<?php

namespace App\Events;

use App\Models\DatasetEmbedding;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatasetEmbeddingProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DatasetEmbedding $embedding;
    public int $currentStep;
    public int $totalSteps;
    public string $currentTask;
    public ?array $additionalData;

    /**
     * Create a new event instance.
     */
    public function __construct(
        DatasetEmbedding $embedding,
        int $currentStep,
        int $totalSteps,
        string $currentTask,
        ?array $additionalData = null
    ) {
        $this->embedding = $embedding;
        $this->currentStep = $currentStep;
        $this->totalSteps = $totalSteps;
        $this->currentTask = $currentTask;
        $this->additionalData = $additionalData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dataset-embedding.' . $this->embedding->dataset->uuid),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $progressPercentage = $this->totalSteps > 0 
            ? round(($this->currentStep / $this->totalSteps) * 100, 2)
            : 0;

        return [
            'embedding_id' => $this->embedding->uuid,
            'dataset_id' => $this->embedding->dataset->uuid,
            'status' => $this->embedding->status,
            'progress_percentage' => $progressPercentage,
            'current_step' => $this->currentStep,
            'total_steps' => $this->totalSteps,
            'current_task' => $this->currentTask,
            'message' => "Processing: {$this->currentTask} ({$this->currentStep}/{$this->totalSteps})",
            'additional_data' => $this->additionalData,
            'updated_at' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'embedding.progress';
    }
}
