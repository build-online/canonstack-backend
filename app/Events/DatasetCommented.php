<?php

namespace App\Events;

use App\Models\Dataset;
use App\Models\Comment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatasetCommented
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Dataset $dataset;
    public Comment $comment;

    /**
     * Create a new event instance.
     */
    public function __construct(Dataset $dataset, Comment $comment)
    {
        $this->dataset = $dataset;
        $this->comment = $comment;
    }
}
