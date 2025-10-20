<?php

namespace App\Events;

use App\Models\DatasetEmbedding;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmbeddingVariantProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DatasetEmbedding $embedding;
    public string $variant;
    public int $progress;
    public string $message;

    /**
     * Create a new event instance.
     */
    public function __construct(DatasetEmbedding $embedding, string $variant, int $progress, string $message)
    {
        $this->embedding = $embedding;
        $this->variant = $variant;
        $this->progress = $progress;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dataset.' . $this->embedding->dataset->uuid),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'embedding_id' => $this->embedding->uuid,
            'dataset_id' => $this->embedding->dataset->uuid,
            'variant' => $this->variant,
            'progress' => $this->progress,
            'message' => $this->message,
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

