<?php

namespace App\Transformers;

use App\Models\ApprovalHistory;
use League\Fractal\TransformerAbstract;

class ApprovalHistoryTransformer extends TransformerAbstract
{
    /**
     * List of resources possible to include
     */
    protected array $availableIncludes = [
        'repository',
        'approver',
    ];

    /**
     * List of resources to automatically include
     */
    protected array $defaultIncludes = [
        'repository',
        'approver',
    ];

    /**
     * Transform the approval history data.
     */
    public function transform(ApprovalHistory $approvalHistory): array
    {
        return [
            'uuid' => $approvalHistory->uuid,
            'action' => $approvalHistory->action,
            'previous_status' => $approvalHistory->previous_status,
            'new_status' => $approvalHistory->new_status,
            'comment' => $approvalHistory->comment,
            'created_at' => $approvalHistory->created_at->toISOString(),
            'updated_at' => $approvalHistory->updated_at->toISOString(),
        ];
    }

    /**
     * Include Repository.
     */
    public function includeRepository(ApprovalHistory $approvalHistory): \League\Fractal\Resource\Item
    {
        if (!$approvalHistory->repository) {
            return $this->null();
        }

        return $this->item($approvalHistory->repository, new SearchRepositoryTransformer());
    }

    /**
     * Include Approver.
     */
    public function includeApprover(ApprovalHistory $approvalHistory): \League\Fractal\Resource\Item
    {
        if (!$approvalHistory->approver) {
            return $this->null();
        }

        return $this->item($approvalHistory->approver, new UserTransformer());
    }
}
