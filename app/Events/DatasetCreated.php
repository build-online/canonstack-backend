<?php

namespace App\Events;

use App\Models\Dataset;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatasetCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Dataset $dataset;

    /**
     * Create a new event instance.
     */
    public function __construct(Dataset $dataset)
    {
        $this->dataset = $dataset;
    }
}
