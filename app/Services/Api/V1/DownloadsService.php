<?php

namespace App\Services\Api\V1;

use App\Models\Download;
use App\Models\Repository;

class DownloadsService
{
    /**
     * Track a download for a repository by the logged-in user.
     */
    public function trackDownload(Repository $repository, int $userId): Download
    {
        return Download::create([
            'user_id' => $userId,
            'repository_id' => $repository->id,
        ]);
    }
}
