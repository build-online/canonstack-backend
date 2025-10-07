<?php

namespace App\Events;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatasetApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Dataset $dataset;
    public User $approver;

    /**
     * Create a new event instance.
     */
    public function __construct(Dataset $dataset, User $approver)
    {
        $this->dataset = $dataset;
        $this->approver = $approver;
    }
}
