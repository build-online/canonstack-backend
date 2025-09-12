<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;

class StatsTransformer extends TransformerAbstract
{
    /**
     * Transform the stats data.
     *
     * @param array $stats
     * @return array
     */
    public function transform(array $stats): array
    {
        return [
            'total_downloads' => (int) $stats['total_downloads'],
            'total_users' => (int) $stats['total_users'],
            'approved_models' => (int) $stats['approved_models'],
            'charged_models' => (int) $stats['charged_models'],
            'approved_datasets' => (int) $stats['approved_datasets'],
            'charged_datasets' => (int) $stats['charged_datasets'],
            'charged_repositories' => (int) $stats['charged_repositories'],
            'approved_repositories' => (int) $stats['approved_repositories'],
        ];
    }
}
