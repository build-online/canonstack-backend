<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Models\ModelRepository;
use App\Services\Api\V1\FileSystemService;
use App\Services\Api\V1\DownloadsService;
use Illuminate\Http\JsonResponse;
use Exception;

class DownloadZipController extends Controller
{
    private FileSystemService $fileSystemService;
    private DownloadsService $downloadsService;

    public function __construct(FileSystemService $fileSystemService, DownloadsService $downloadsService)
    {
        $this->fileSystemService = $fileSystemService;
        $this->downloadsService = $downloadsService;
    }

    /**
     * Get download URL for the complete ZIP file.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $model = ModelRepository::where('uuid', $uuid)->firstOrFail();
        $repository = $model->repository;

        try {
            $downloadUrl = $this->fileSystemService->getZipDownloadUrl($repository);
            $this->downloadsService->trackDownload($repository, auth()->id());
            
            return response()->sendResponse([
                'download_url' => $downloadUrl,
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
