<?php

namespace App\Events;

use App\Models\ModelRepository;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ModelRepository $model;

    /**
     * Create a new event instance.
     */
    public function __construct(ModelRepository $model)
    {
        $this->model = $model;
    }
}
