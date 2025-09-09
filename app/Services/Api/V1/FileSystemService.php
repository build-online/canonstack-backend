<?php

namespace App\Services\Api\V1;

use App\Models\Repository;
use App\Models\RepositoryFile;
use App\Utils\FileSystemUtils;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;
use Exception;

class FileSystemService
{
    private string $contentType;

    public function __construct(string $contentType = 'models')
    {
        $this->contentType = $contentType; // 'models' or 'datasets'
    }

    /**
     * Update the content type for this service instance.
     * 
     * @param string $contentType The new content type ('models' or 'datasets')
     * @return void
     */
    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    /**
     * Get configuration value for current content type.
     */
    private function getConfig(string $key, mixed $default = null): mixed
    {
        return config("filesystem_limits.{$this->contentType}.{$key}", $default);
    }

    /**
     * Get allowed extensions for current content type.
     */
    private function getAllowedExtensions(): array
    {
        return config("filesystem_limits.allowed_extensions.{$this->contentType}", []);
    }

    /**
     * Get human readable size label.
     */
    private function getSizeLabel(string $key): string
    {
        return config("filesystem_limits.size_labels.{$this->contentType}.{$key}", 'Unknown');
    }

    /**
     * Extract ZIP contents and store file structure in database.
     */
    public function extractAndStoreZipContents(Repository $repository): void
    {
        DB::beginTransaction();
        
        $tempZipPath = null;
        $extractPath = null;
        
        try {
            // Extract and validate ZIP contents
            $tempZipPath = $this->downloadZipTemporarily($repository);
            $extractionResult = $this->extractZipSecurely($tempZipPath, $repository);
            
            $fileStructure = $extractionResult['files'];
            $extractPath = $extractionResult['extract_path'];
            
            // Store file structure in database
            $this->storeFileStructure($repository, $fileStructure);
            
            // Upload individual files to storage
            $this->uploadIndividualFiles($repository, $fileStructure, $tempZipPath);
            
            // Clean up temporary files immediately after upload
            FileSystemUtils::cleanupTemporaryFiles($extractPath); // Clean up extraction directory
            FileSystemUtils::cleanupTemporaryFiles($tempZipPath); // Clean up ZIP file
            
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            
            if ($tempZipPath) {
                FileSystemUtils::cleanupTemporaryFiles($tempZipPath);
            }
            if ($extractPath) {
                FileSystemUtils::cleanupTemporaryFiles($extractPath);
            }
            
            throw $e;
        }
    }

    /**
     * Get file structure for a repository with navigation support.
     */
    public function getFileStructure(Repository $repository, ?string $parentPath = null): array
    {
        $fielsQuery = $repository->files();
        
        if ($parentPath === null) {
            $fielsQuery->root();
        } else {
            $fielsQuery->where('parent_path', $parentPath);
        }
        
        $files = $fielsQuery->orderBy('type', 'desc') // folders first
                      ->orderBy('name', 'asc')
                      ->get();
        
        $items = $files->map(function ($file) use ($repository) {
            return [
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
                'children_count' => $file->type === 'folder' ? $this->getFolderChildrenCount($repository, $file->path) : null,
            ];
        })->toArray();
        
        return [
            'items' => $items,
            'current_path' => $parentPath,
            'is_root' => $parentPath === null,
        ];
    }

    /**
     * Get the number of children (files + folders) for a folder.
     */
    private function getFolderChildrenCount(Repository $repository, string $folderPath): int
    {
        return $repository->files()
                         ->where('parent_path', $folderPath)
                         ->count();
    }

    /**
     * Format bytes to human readable format.
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        return FileSystemUtils::formatBytes($bytes, $precision);
    }

    /**
     * Get download URL for individual file.
     */
    public function getFileDownloadUrl(RepositoryFile $file): string
    {
        if ($file->type !== 'file' || !$file->file_ref) {
            throw new Exception('Cannot download folder or file without reference.');
        }

        return Storage::temporaryUrl(
            $file->file_ref,
            now()->addHours(1)
        );
    }

    /**
     * Get download URL for whole ZIP.
     */
    public function getZipDownloadUrl(Repository $repository): string
    {
        return Storage::temporaryUrl(
            $repository->file_ref,
            now()->addHours(1)
        );
    }

    /**
     * Get file content for preview (text files only).
     */
    public function getFileContent(RepositoryFile $file): array
    {
        if ($file->type !== 'file' || !$file->file_ref) {
            throw new Exception('Cannot get content of folder or file without reference.');
        }

        if (!$this->isPreviewableFile($file)) {
            throw new Exception('File type is not previewable. Supported types: text, code, config files.');
        }

        $maxPreviewSize = 1024 * 1024; // 1MB limit for preview
        $isTruncated = false;
        $previewSize = $file->size;

        if ($file->size > $maxPreviewSize) {
            $previewSize = $maxPreviewSize;
            $isTruncated = true;
        }

        try {
            $content = Storage::get($file->file_ref);
            
            if ($isTruncated) {
                $content = substr($content, 0, $maxPreviewSize);
                $content .= "\n\n... [Content truncated - Download full file to see complete content] ...";
            }

            $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'ASCII'], true);
            $isText = $encoding !== false && FileSystemUtils::isTextContent($content);

            if (!$isText) {
                throw new Exception('File appears to be binary and cannot be previewed as text.');
            }

            if ($encoding && $encoding !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            }

            $lineCount = substr_count($content, "\n") + 1;

            return [
                'content' => $content,
                'is_text' => $isText,
                'encoding' => $encoding ?: 'UTF-8',
                'line_count' => $lineCount,
                'is_truncated' => $isTruncated,
                'preview_size' => strlen($content),
            ];

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'File appears to be binary') !== false) {
                throw $e;
            }
            throw new Exception('Failed to retrieve file content: ' . $e->getMessage());
        }
    }

    /**
     * Check if file type is previewable.
     */
    public function isPreviewableFile(RepositoryFile $file): bool
    {
        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        
        $previewableExtensions = [
            'txt', 'text', 'readme', 'md', 'markdown', 'rst',
            'py', 'js', 'ts', 'php', 'java', 'cpp', 'c', 'h', 'cs', 'rb', 'go', 'rs', 'swift',
            'html', 'htm', 'css', 'scss', 'sass', 'less',
            'json', 'yaml', 'yml', 'toml', 'ini', 'cfg', 'config', 'conf',
            'xml', 'plist', 'properties',
            'csv', 'tsv', 'sql', 'log',
            'license', 'changelog', 'authors', 'contributors', 'notice',
            'sh', 'bash', 'zsh', 'fish', 'ps1', 'bat', 'cmd',
            'dockerfile', 'gitignore', 'editorconfig', 'htaccess',
        ];

        if (in_array($extension, $previewableExtensions)) {
            return true;
        }

        if ($file->mime_type) {
            $textMimeTypes = [
                'text/plain', 'text/html', 'text/css', 'text/javascript',
                'text/xml', 'text/csv', 'text/markdown',
                'application/json', 'application/xml', 'application/yaml',
                'application/x-yaml', 'application/x-sh', 'application/x-python',
            ];

            foreach ($textMimeTypes as $mimeType) {
                if (strpos($file->mime_type, $mimeType) === 0) {
                    return true;
                }
            }
        }

        $commonTextFiles = ['readme', 'license', 'changelog', 'authors', 'dockerfile', 'makefile'];
        if (in_array(strtolower($file->name), $commonTextFiles)) {
            return true;
        }

        return false;
    }


    /**
     * Download ZIP file temporarily for processing.
     */
    private function downloadZipTemporarily(Repository $repository): string
    {
        $tempPath = storage_path('app/temp/' . Str::uuid() . '.zip');
        
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        
        $zipContent = Storage::get($repository->file_ref);
        file_put_contents($tempPath, $zipContent);
        
        return $tempPath;
    }

    /**
     * Extract ZIP file securely with validation.
     * Returns array with file structure and extraction path for cleanup.
     */
    private function extractZipSecurely(string $zipPath, Repository $repository): array
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath);
        
        if (!$result) {
            throw new Exception('Failed to open ZIP file: ' . FileSystemUtils::getZipError($result));
        }
        
        $fileStructure = [];
        $totalSize = 0;
        $extractPath = storage_path('app/temp/' . Str::uuid());
        
        if (!mkdir($extractPath, 0755, true)) {
            $zip->close();
            throw new Exception('Failed to create extraction directory.');
        }
        
        try {
            // Validate file count to prevent ZIP bombs
            $maxFileCount = $this->getConfig('max_file_count');
            if ($zip->numFiles > $maxFileCount) {
                throw new Exception("ZIP contains too many files. Maximum allowed: {$maxFileCount}, found: {$zip->numFiles}");
            }
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $filename = $stat['name'];
                
                if (FileSystemUtils::isSystemFile($filename)) {
                    continue;
                }
                
                FileSystemUtils::validateFilePath($filename);
                FileSystemUtils::validateFileSize($stat['size'], $this->contentType);
                
                $totalSize += $stat['size'];
                $maxTotalSize = $this->getConfig('max_total_extracted_size');
                $maxTotalSizeLabel = $this->getSizeLabel('max_total_extracted_size');
                
                if ($totalSize > $maxTotalSize) {
                    throw new Exception("Total extracted size exceeds limit of {$maxTotalSizeLabel}.");
                }
                
                $isFolder = substr($filename, -1) === '/';
                
                if ($isFolder) {
                    $fileStructure[] = [
                        'name' => basename(rtrim($filename, '/')),
                        'path' => rtrim($filename, '/'),
                        'type' => 'folder',
                        'size' => null,
                        'mime_type' => null,
                        'parent_path' => FileSystemUtils::getParentPath($filename),
                        'file_ref' => null,
                    ];
                } else {
                    $extractedFilePath = $extractPath . '/' . $filename;
                    $dir = dirname($extractedFilePath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    
                    if (!$zip->extractTo($extractPath, $filename)) {
                        throw new Exception('Failed to extract file: ' . $filename);
                    }
                    
                    FileSystemUtils::validateFileExtension($filename, $this->contentType);
                    $mimeType = FileSystemUtils::getMimeType($extractedFilePath);
                    
                    $fileStructure[] = [
                        'name' => basename($filename),
                        'path' => $filename,
                        'type' => 'file',
                        'size' => $stat['size'],
                        'mime_type' => $mimeType,
                        'parent_path' => FileSystemUtils::getParentPath($filename),
                        'file_ref' => null,
                        'local_path' => $extractedFilePath,
                    ];
                }
            }
            
            $zip->close();
            
            // Create missing parent folders
            $fileStructure = $this->ensureParentFolders($fileStructure);
            
            return [
                'files' => $fileStructure,
                'extract_path' => $extractPath
            ];
            
        } catch (Exception $e) {
            $zip->close();
            FileSystemUtils::cleanupTemporaryFiles($extractPath);
            throw $e;
        }
    }



    /**
     * Ensure all parent folders exist in the structure.
     */
    private function ensureParentFolders(array $fileStructure): array
    {
        $existingPaths = array_column($fileStructure, 'path');
        $newFolders = [];
        
        foreach ($fileStructure as $item) {
            $parentPath = $item['parent_path'];
            
            while ($parentPath && !in_array($parentPath, $existingPaths)) {
                $newFolders[] = [
                    'name' => basename($parentPath),
                    'path' => $parentPath,
                    'type' => 'folder',
                    'size' => null,
                    'mime_type' => null,
                    'parent_path' => FileSystemUtils::getParentPath($parentPath),
                    'file_ref' => null,
                ];
                
                $existingPaths[] = $parentPath;
                $parentPath = FileSystemUtils::getParentPath($parentPath);
            }
        }
        
        return array_merge($fileStructure, $newFolders);
    }

    /**
     * Store file structure in database.
     */
    private function storeFileStructure(Repository $repository, array $fileStructure): void
    {
        foreach ($fileStructure as $item) {
            RepositoryFile::create([
                'repository_id' => $repository->id,
                'name' => $item['name'],
                'path' => $item['path'],
                'type' => $item['type'],
                'size' => $item['size'],
                'mime_type' => $item['mime_type'],
                'parent_path' => $item['parent_path'],
                'file_ref' => $item['file_ref'],
            ]);
        }
    }

    /**
     * Upload individual files to Storage.
     */
    private function uploadIndividualFiles(Repository $repository, array $fileStructure, string $tempZipPath): void
    {
        foreach ($fileStructure as $item) {
            if ($item['type'] === 'file' && isset($item['local_path'])) {
                $fileRef = $this->generateIndividualFileRef($repository, $item['path']);
                
                Storage::putFileAs(
                    dirname($fileRef),
                    new \Illuminate\Http\File($item['local_path']),
                    basename($fileRef),
                    'private'
                );
                
                RepositoryFile::where('repository_id', $repository->id)
                            ->where('path', $item['path'])
                            ->update(['file_ref' => $fileRef]);
            }
        }
    }

    /**
     * Generate file reference for individual file.
     */
    private function generateIndividualFileRef(Repository $repository, string $filePath): string
    {
        $timestamp = now()->format('Y-m-d');
        $uuid = Str::uuid();
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $extension = $extension ? '.' . $extension : '';
        
        return "models/{$timestamp}/{$repository->uuid}/{$uuid}{$extension}";
    }

}
