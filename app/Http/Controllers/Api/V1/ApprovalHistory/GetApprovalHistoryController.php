<?php

namespace App\Http\Controllers\Api\V1\ApprovalHistory;

use App\Http\Controllers\Controller;
use App\Services\Api\V1\ApprovalHistoryService;
use App\Transformers\ApprovalHistoryTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class GetApprovalHistoryController extends Controller
{
    private ApprovalHistoryService $approvalHistoryService;
    private ApprovalHistoryTransformer $approvalHistoryTransformer;

    public function __construct(
        ApprovalHistoryService $approvalHistoryService,
        ApprovalHistoryTransformer $approvalHistoryTransformer
    ) {
        $this->approvalHistoryService = $approvalHistoryService;
        $this->approvalHistoryTransformer = $approvalHistoryTransformer;
    }

    /**
     * Get approval history for the authenticated approver.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($user->role !== 'APPROVER') {
                return response()->sendError(
                    'Access denied. Only approvers can view approval history.',
                    403
                );
            }

            $filters = [
                'action' => $request->get('action'),
                'repository_type' => $request->get('repository_type'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'per_page' => $request->get('per_page', 15),
                'page' => $request->get('page', 1),
            ];

            $filters = array_filter($filters, function($value) {
                return $value !== null;
            });

            $approvalHistory = $this->approvalHistoryService->getApproverHistory($user, $filters);

            return response()->sendResponse(
                $approvalHistory,
                $this->approvalHistoryTransformer,
                'Approval history retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                500
            );
        }
    }
}
