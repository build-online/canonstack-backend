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
        $repository = $model->repository;

        try {
            return response()->sendResponse([
                'download_url' => $this->fileSystemService->getZipDownloadUrl($repository),
                'expires_in' => '1 hour'
            ], null, 'Download URL generated successfully');
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
