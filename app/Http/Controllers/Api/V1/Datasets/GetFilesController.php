<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\FileSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class GetFilesController extends Controller
{
    private FileSystemService $fileSystemService;

    public function __construct(FileSystemService $fileSystemService)
    {
        $this->fileSystemService = $fileSystemService;
        $this->fileSystemService->setContentType('datasets');
    }

    /**
     * Get file structure for a dataset repository.
     */
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $dataset = Dataset::where('uuid', $uuid)->firstOrFail();
        $repository = $dataset->repository;
        $parentPath = $request->query('path');
        
        try {
            $navigationData = $this->fileSystemService->getFileStructure($repository, $parentPath);
            
            return response()->sendResponse([
                'items' => $navigationData['items'],
                'current_path' => $navigationData['current_path'],
                'is_root' => $navigationData['is_root'],
                'repository' => [
                    'uuid' => $repository->uuid,
                    'name' => $repository->name,
                    'status' => $repository->status,
                ]
            ], null, 'File structure retrieved successfully');
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
