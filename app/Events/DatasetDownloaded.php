<?php

namespace App\Events;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatasetDownloaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Dataset $dataset;
    public User $downloader;

    /**
     * Create a new event instance.
     */
    public function __construct(Dataset $dataset, User $downloader)
    {
        $this->dataset = $dataset;
        $this->downloader = $downloader;
    }
}
