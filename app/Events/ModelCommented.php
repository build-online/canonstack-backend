<?php

namespace App\Events;

use App\Models\ModelRepository;
use App\Models\Comment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelCommented
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ModelRepository $model;
    public Comment $comment;

    /**
     * Create a new event instance.
     */
    public function __construct(ModelRepository $model, Comment $comment)
    {
        $this->model = $model;
        $this->comment = $comment;
    }
}
