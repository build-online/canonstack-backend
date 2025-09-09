<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Models\RepositoryFile;
use App\Services\Api\V1\FileSystemService;
use Illuminate\Http\JsonResponse;
use Exception;

class GetFileContentController extends Controller
{
    private FileSystemService $fileSystemService;

    public function __construct(FileSystemService $fileSystemService)
    {
        $this->fileSystemService = $fileSystemService;
    }

    /**
     * Get the content of a text file for preview.
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
        
        // Ensure this is a file, not a folder
        if ($file->type !== 'file') {
            return response()->sendError(
                'Cannot view content of a folder.',
                400
            );
        }

        // Check if file has a reference (was uploaded)
        if (!$file->file_ref) {
            return response()->sendError(
                'File reference not found.',
                404
            );
        }

        try {
            $contentData = $this->fileSystemService->getFileContent($file);
            
            return response()->sendResponse([
                'file' => [
                    'uuid' => $file->uuid,
                    'name' => $file->name,
                    'path' => $file->path,
                    'size' => $file->size,
                    'human_size' => $file->human_size,
                    'mime_type' => $file->mime_type,
                ],
                'content' => $contentData['content'],
                'is_text' => $contentData['is_text'],
                'encoding' => $contentData['encoding'],
                'line_count' => $contentData['line_count'],
                'is_truncated' => $contentData['is_truncated'],
                'preview_size' => $contentData['preview_size'],
            ]);
            
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
