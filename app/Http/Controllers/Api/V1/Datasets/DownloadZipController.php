<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\DownloadsService;
use App\Services\Api\V1\FileSystemService;
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
     * Generate download URL for dataset ZIP file.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $dataset = Dataset::where('uuid', $uuid)->with('repository')->firstOrFail();
        $repository = $dataset->repository;
        
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
