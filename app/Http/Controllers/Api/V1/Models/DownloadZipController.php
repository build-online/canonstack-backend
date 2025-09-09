<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Models\ModelRepository;
use App\Services\Api\V1\FileSystemService;
use Illuminate\Http\JsonResponse;
use Exception;

class DownloadZipController extends Controller
{
    private FileSystemService $fileSystemService;

    public function __construct(FileSystemService $fileSystemService)
    {
        $this->fileSystemService = $fileSystemService;
    }

    /**
     * Get download URL for the complete ZIP file.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $model = ModelRepository::where('uuid', $uuid)->firstOrFail();
        // Ensure this repository has a model (is a model repository)
        if (!$model) {
            return response()->sendError(
                'Model not found.',
                404
            );
        }

        $repository = $model->repository;
        if (!$repository) {
            return response()->sendError(
                'Repository not found.',
                404
            );
        }

        try {
            $downloadUrl = $this->fileSystemService->getZipDownloadUrl($repository);
            
            return response()->sendResponse([
                'download_url' => $downloadUrl,
                'repository' => [
                    'uuid' => $repository->uuid,
                    'name' => $repository->name,
                    'file_ref' => $repository->file_ref,
                ],
                'expires_in' => '1 hour'
            ]);
            
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
