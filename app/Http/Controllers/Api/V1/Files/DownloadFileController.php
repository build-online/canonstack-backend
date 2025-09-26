<?php

namespace App\Http\Controllers\Api\V1\Files;

use App\Http\Controllers\Controller;
use App\Models\RepositoryFile;
use App\Services\Api\V1\FileSystemService;
use Illuminate\Http\JsonResponse;
use Exception;

class DownloadFileController extends Controller
{
    private FileSystemService $fileSystemService;

    public function __construct(FileSystemService $fileSystemService)
    {
        $this->fileSystemService = $fileSystemService;
    }

    /**
     * Get download URL for a specific file.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $file = RepositoryFile::where('uuid', $uuid)->firstOrFail();

        try {
            return response()->sendResponse([
                'download_url' => $this->fileSystemService->getFileDownloadUrl($file),
                'expires_in' => '1 hour'
            ], null, 'Link generated successfully');
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
