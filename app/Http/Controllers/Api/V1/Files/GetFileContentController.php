<?php

namespace App\Http\Controllers\Api\V1\Files;

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
            ], null, 'File content retrieved successfully');
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
