<?php

namespace App\Events;

use App\Models\ModelRepository;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ModelRepository $model;
    public User $approver;

    /**
     * Create a new event instance.
     */
    public function __construct(ModelRepository $model, User $approver)
    {
        $this->model = $model;
        $this->approver = $approver;
    }
}
