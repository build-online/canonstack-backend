<?php

namespace App\Services\Api\V1;

use App\Models\User;
use App\Models\Download;
use App\Models\ModelRepository;
use App\Models\Dataset;
use App\Models\Repository;
use App\Models\Like;
use Exception;

class StatsService
{
    /**
     * Get general application statistics.
     * 
     * @return array
     * @throws Exception
     */
    public function getGeneralStats(): array
    {
        try {
            $chargedModels = $this->getChargedModels();
            $chargedDatasets = $this->getChargedDatasets();
            $approvedModels = $this->getApprovedModels();
            $approvedDatasets = $this->getApprovedDatasets();
            
            return [
                'total_downloads' => $this->getTotalDownloads(),
                'total_users' => $this->getTotalUsers(),
                'total_likes' => $this->getTotalLikes(),
                'approved_models' => $approvedModels,
                'charged_models' => $chargedModels,
                'approved_datasets' => $approvedDatasets,
                'charged_datasets' => $chargedDatasets,
                'charged_repositories' => $chargedModels + $chargedDatasets,
                'approved_repositories' => $approvedModels + $approvedDatasets,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get total number of downloads across all repositories.
     */
    private function getTotalDownloads(): int
    {
        return Download::count();
    }

    /**
     * Get total number of users.
     */
    private function getTotalUsers(): int
    {
        return User::count();
    }

    /**
     * Get number of approved models (repositories with status = 'ACCEPTED' that have a model).
     */
    private function getApprovedModels(): int
    {
        return Repository::where('status', 'ACCEPTED')
            ->whereHas('model')
            ->count();
    }

    /**
     * Get number of uploaded models (total models in the system).
     */
    private function getChargedModels(): int
    {
        return ModelRepository::count();
    }

    /**
     * Get number of approved datasets (repositories with status = 'ACCEPTED' that have a dataset).
     */
    private function getApprovedDatasets(): int
    {
        return Repository::where('status', 'ACCEPTED')
            ->whereHas('dataset')
            ->count();
    }

    /**
     * Get number of uploaded datasets (total datasets in the system).
     */
    private function getChargedDatasets(): int
    {
        return Dataset::count();
    }

    /**
     * Get total number of likes across all repositories.
     */
    private function getTotalLikes(): int
    {
        return Like::count();
    }
}
