<?php

namespace App\Http\Controllers\Api\V1\Models;

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
        if (!$file) {
            return response()->sendError(
                'File not found.',
                404
            );
        }
        
        if ($file->type !== 'file') {
            return response()->sendError(
                'Cannot download a folder.',
                400
            );
        }

        // Check if user can access this file
        // You can add authorization logic here if needed
        
        try {
            $downloadUrl = $this->fileSystemService->getFileDownloadUrl($file);
            
            return response()->sendResponse([
                'download_url' => $downloadUrl,
                'file' => [
                    'uuid' => $file->uuid,
                    'name' => $file->name,
                    'size' => $file->size,
                    'human_size' => $file->human_size,
                    'mime_type' => $file->mime_type,
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
