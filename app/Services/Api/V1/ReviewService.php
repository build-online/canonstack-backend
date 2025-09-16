<?php

namespace App\Services\Api\V1;

use App\Models\Repository;
use App\Models\ModelRepository;
use App\Models\Dataset;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class ReviewService
{
    /**
     * Review a model (approve or decline).
     */
    public function reviewRepository(Repository $repository, array $reviewData, User $approver): ModelRepository | Dataset
    {
        return DB::transaction(function () use ($reviewData, $approver, $repository) {
            $this->validateApproverAuthority($approver, $repository);
            $this->updateRepositoryStatus($repository, $reviewData['action'], $approver);
            
            if ($reviewData['comment']) {
                $this->createApprovalComment($repository, $reviewData['comment'], $approver);
            }
            
            if ($repository->model) {
                $model = $repository->model;
                return $model->fresh(['repository.user', 'repository.category']);
            }

            $dataset = $repository->dataset;
            return $dataset->fresh(['repository.user', 'repository.category']);
        });
    }

    /**
     * Validate that the approver has authority to review this repository.
     * All approvers can now review any repository.
     */
    public function validateApproverAuthority(User $approver, Repository $repository): void
    {
        if ($approver->role !== 'APPROVER') {
            throw new Exception('User must be an approver to review repositories.');
        }

        if ($repository->status !== 'PENDING_REVIEW') {
            throw new Exception('Repository is not in pending review status.');
        }
    }

    /**
     * Update repository status and set approved_by field.
     */
    public function updateRepositoryStatus(Repository $repository, string $action, User $approver): void
    {
        $status = $action === 'APPROVE' ? 'ACCEPTED' : 'DECLINED';
        
        $repository->update([
            'status' => $status,
            'approved_by' => $approver->id,
        ]);
    }

    /**
     * Create an approval process comment.
     */
    public function createApprovalComment(Repository $repository, string $commentText, User $approver): Comment
    {
        return Comment::create([
            'user_id' => $approver->id,
            'repository_id' => $repository->id,
            'text' => $commentText,
            'is_approver' => true, // Since this is from an approver
            'is_from_approval_process' => true,
        ]);
    }
}
