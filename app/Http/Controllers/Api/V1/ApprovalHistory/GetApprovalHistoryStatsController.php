<?php

namespace App\Http\Controllers\Api\V1\ApprovalHistory;

use App\Http\Controllers\Controller;
use App\Services\Api\V1\ApprovalHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class GetApprovalHistoryStatsController extends Controller
{
    private ApprovalHistoryService $approvalHistoryService;

    public function __construct(ApprovalHistoryService $approvalHistoryService)
    {
        $this->approvalHistoryService = $approvalHistoryService;
    }

    /**
     * Get approval statistics for the authenticated approver.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($user->role !== 'APPROVER') {
                return response()->sendError(
                    'Access denied. Only approvers can view approval statistics.',
                    403
                );
            }

            $dateRange = [];
            if ($request->has('date_from')) {
                $dateRange['from'] = $request->get('date_from');
            }
            if ($request->has('date_to')) {
                $dateRange['to'] = $request->get('date_to');
            }

            $stats = $this->approvalHistoryService->getApproverStats($user, $dateRange);

            return response()->sendResponse(
                $stats,
                null,
                'Approval statistics retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                500
            );
        }
    }
}
