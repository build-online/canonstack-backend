<?php

namespace App\Services\Api\V1;

use App\Models\ApprovalHistory;
use App\Models\User;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class ApprovalHistoryService
{
    /**
     * Get approval history for a specific approver.
     */
    public function getApproverHistory(User $approver, array $filters = []): LengthAwarePaginator
    {
        $query = ApprovalHistory::with([
            'repository:id,uuid,name,status',
            'repository.user:id,uuid,name,username',
            'repository.category:id,uuid,name',
            'repository.model:id,repository_id',
            'repository.dataset:id,repository_id'
        ])
        ->byApprover($approver->id)
        ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['action']) && in_array($filters['action'], ['APPROVE', 'REJECT'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['repository_type'])) {
            if ($filters['repository_type'] === 'model') {
                $query->whereHas('repository.model');
            } elseif ($filters['repository_type'] === 'dataset') {
                $query->whereHas('repository.dataset');
            }
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Create an approval history record.
     */
    public function createApprovalHistory(
        Repository $repository,
        User $approver,
        string $action,
        string $previousStatus,
        string $newStatus,
        ?string $comment = null
    ): ApprovalHistory {
        return ApprovalHistory::create([
            'repository_id' => $repository->id,
            'approver_id' => $approver->id,
            'action' => $action,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'comment' => $comment,
        ]);
    }

    /**
     * Get approval statistics for an approver.
     */
    public function getApproverStats(User $approver, array $dateRange = []): array
    {
        $query = ApprovalHistory::byApprover($approver->id);

        if (!empty($dateRange)) {
            if (isset($dateRange['from'])) {
                $query->whereDate('created_at', '>=', $dateRange['from']);
            }
            if (isset($dateRange['to'])) {
                $query->whereDate('created_at', '<=', $dateRange['to']);
            }
        }

        $totalReviews = $query->count();
        $approvals = $query->clone()->approvals()->count();
        $rejections = $query->clone()->rejections()->count();

        return [
            'total_reviews' => $totalReviews,
            'total_approvals' => $approvals,
            'total_rejections' => $rejections,
            'approval_rate' => $totalReviews > 0 ? round(($approvals / $totalReviews) * 100, 2) : 0,
        ];
    }
}
