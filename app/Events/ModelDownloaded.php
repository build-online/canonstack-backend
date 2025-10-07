<?php

namespace App\Events;

use App\Models\ModelRepository;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelDownloaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ModelRepository $model;
    public User $downloader;

    /**
     * Create a new event instance.
     */
    public function __construct(ModelRepository $model, User $downloader)
    {
        $this->model = $model;
        $this->downloader = $downloader;
    }
}
