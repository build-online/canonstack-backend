<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Models\ModelRepository;
use App\Models\Repository;
use App\Services\Api\V1\FileSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class GetFileTreeController extends Controller
{
    private FileSystemService $fileSystemService;

    public function __construct(FileSystemService $fileSystemService)
    {
        $this->fileSystemService = $fileSystemService;
    }

    /**
     * Get complete file tree structure for a model repository.
     * This returns a hierarchical tree useful for tree view components.
     */
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $model = ModelRepository::where('uuid', $uuid)->firstOrFail();
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

        // Optional: Limit tree depth to prevent large responses
        $maxDepth = $request->query('max_depth', 5);
        
        try {
            $tree = $this->buildFileTree($repository, null, 0, $maxDepth);
            $stats = $this->getRepositoryStats($repository);
            
            return response()->sendResponse([
                'tree' => $tree,
                'stats' => $stats,
                'repository' => [
                    'uuid' => $repository->uuid,
                    'name' => $repository->name,
                    'status' => $repository->status,
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * Build a hierarchical file tree.
     */
    private function buildFileTree(Repository $repository, ?string $parentPath, int $currentDepth, int $maxDepth): array
    {
        if ($currentDepth >= $maxDepth) {
            return [];
        }

        $query = $repository->files();
        
        if ($parentPath === null) {
            $query->root();
        } else {
            $query->where('parent_path', $parentPath);
        }
        
        $files = $query->orderBy('type', 'desc') // folders first
                      ->orderBy('name', 'asc')
                      ->get();

        return $files->map(function ($file) use ($repository, $currentDepth, $maxDepth) {
            $item = [
                'uuid' => $file->uuid,
                'name' => $file->name,
                'path' => $file->path,
                'type' => $file->type,
                'size' => $file->size,
                'human_size' => $file->human_size,
                'mime_type' => $file->mime_type,
                'parent_path' => $file->parent_path,
                'created_at' => $file->created_at->toISOString(),
                'is_downloadable' => $file->type === 'file' && !empty($file->file_ref),
                'is_previewable' => $file->type === 'file' ? $this->isPreviewableFile($file) : false,
                'depth' => $currentDepth,
            ];

            // If it's a folder, recursively get children
            if ($file->type === 'folder') {
                $children = $this->buildFileTree($repository, $file->path, $currentDepth + 1, $maxDepth);
                $item['children'] = $children;
                $item['children_count'] = count($children);
                $item['has_children'] = count($children) > 0;
            } else {
                $item['children'] = [];
                $item['children_count'] = 0;
                $item['has_children'] = false;
            }

            return $item;
        })->toArray();
    }

    /**
     * Get overall repository statistics.
     */
    private function getRepositoryStats(Repository $repository): array
    {
        $allFiles = $repository->files;
        
        $folderCount = $allFiles->where('type', 'folder')->count();
        $fileCount = $allFiles->where('type', 'file')->count();
        $totalSize = $allFiles->where('type', 'file')->sum('size');
        
        // Calculate depth statistics
        $maxDepth = 0;
        foreach ($allFiles as $file) {
            $depth = substr_count($file->path, '/');
            $maxDepth = max($maxDepth, $depth);
        }

        return [
            'total_folders' => $folderCount,
            'total_files' => $fileCount,
            'total_items' => $folderCount + $fileCount,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'max_depth' => $maxDepth + 1, // +1 because root is depth 0
        ];
    }

    /**
     * Check if file type is previewable.
     */
    private function isPreviewableFile($file): bool
    {
        // Get file extension
        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        
        // Define previewable extensions
        $previewableExtensions = [
            // Text files
            'txt', 'text', 'readme', 'md', 'markdown', 'rst',
            
            // Code files
            'py', 'js', 'ts', 'php', 'java', 'cpp', 'c', 'h', 'cs', 'rb', 'go', 'rs', 'swift',
            'html', 'htm', 'css', 'scss', 'sass', 'less',
            
            // Config files
            'json', 'yaml', 'yml', 'toml', 'ini', 'cfg', 'config', 'conf',
            'xml', 'plist', 'properties',
            
            // Data files
            'csv', 'tsv', 'sql', 'log',
            
            // Documentation
            'license', 'changelog', 'authors', 'contributors', 'notice',
            
            // Shell scripts
            'sh', 'bash', 'zsh', 'fish', 'ps1', 'bat', 'cmd',
            
            // Other common text formats
            'dockerfile', 'gitignore', 'editorconfig', 'htaccess',
        ];

        // Check by extension
        if (in_array($extension, $previewableExtensions)) {
            return true;
        }

        // Check common files without extensions
        $commonTextFiles = ['readme', 'license', 'changelog', 'authors', 'dockerfile', 'makefile'];
        if (in_array(strtolower($file->name), $commonTextFiles)) {
            return true;
        }

        return false;
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $factor = floor(log($bytes, 1024));
        
        return round($bytes / (1024 ** $factor), $precision) . ' ' . $units[$factor];
    }
}
