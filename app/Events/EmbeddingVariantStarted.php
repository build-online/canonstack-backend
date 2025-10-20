<?php

namespace App\Events;

use App\Models\DatasetEmbedding;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmbeddingVariantStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DatasetEmbedding $embedding;
    public string $variant;

    /**
     * Create a new event instance.
     */
    public function __construct(DatasetEmbedding $embedding, string $variant)
    {
        $this->embedding = $embedding;
        $this->variant = $variant;
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
            'status' => 'PROCESSING',
            'message' => ucfirst($this->variant) . ' embedding generation started',
            'collection' => $this->embedding->qdrant_collection_name,
            'started_at' => $this->embedding->processing_started_at?->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'embedding.started';
    }
}

