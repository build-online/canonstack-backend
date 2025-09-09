<?php

namespace App\Services\Api\V1;

use App\Models\Repository;
use App\Models\RepositoryFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;
use Exception;
use finfo;

class FileSystemService
{
    private string $contentType;

    public function __construct(string $contentType = 'models')
    {
        $this->contentType = $contentType; // 'models' or 'datasets'
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
        
        try {
            // Download ZIP from Wasabi to temporary location
            $tempZipPath = $this->downloadZipTemporarily($repository);
            
            // Extract and validate ZIP contents
            $fileStructure = $this->extractZipSecurely($tempZipPath, $repository);
            
            // Store file structure in database
            $this->storeFileStructure($repository, $fileStructure);
            
            // Upload individual files to Wasabi
            $this->uploadIndividualFiles($repository, $fileStructure, $tempZipPath);
            
            // Clean up temporary files
            $this->cleanupTemporaryFiles($tempZipPath);
            
            DB::commit();
            
        } catch (Exception $e) {
            DB::rollBack();
            
            // Clean up on error
            if (isset($tempZipPath)) {
                $this->cleanupTemporaryFiles($tempZipPath);
            }
            
            throw $e;
        }
    }

    /**
     * Get file structure for a repository with navigation support.
     */
    public function getFileStructure(Repository $repository, ?string $parentPath = null): array
    {
        $query = $repository->files();
        
        if ($parentPath === null) {
            $query->root();
        } else {
            $query->where('parent_path', $parentPath);
        }
        
        $files = $query->orderBy('type', 'desc') // folders first
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
        
        // Generate breadcrumbs for navigation
        $breadcrumbs = $this->generateBreadcrumbs($repository, $parentPath);
        
        // Get folder statistics
        $stats = $this->getFolderStats($repository, $parentPath);
        
        return [
            'items' => $items,
            'breadcrumbs' => $breadcrumbs,
            'stats' => $stats,
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
     * Generate breadcrumbs for navigation.
     */
    private function generateBreadcrumbs(Repository $repository, ?string $currentPath): array
    {
        $breadcrumbs = [
            [
                'name' => 'Root',
                'path' => null,
                'is_current' => $currentPath === null,
            ]
        ];
        
        if ($currentPath) {
            $pathParts = explode('/', $currentPath);
            $buildPath = '';
            
            foreach ($pathParts as $index => $part) {
                $buildPath .= ($index > 0 ? '/' : '') . $part;
                $isLast = $index === count($pathParts) - 1;
                
                $breadcrumbs[] = [
                    'name' => $part,
                    'path' => $buildPath,
                    'is_current' => $isLast,
                ];
            }
        }
        
        return $breadcrumbs;
    }

    /**
     * Get statistics for current folder.
     */
    private function getFolderStats(Repository $repository, ?string $parentPath): array
    {
        $query = $repository->files();
        
        if ($parentPath === null) {
            $query->root();
        } else {
            $query->where('parent_path', $parentPath);
        }
        
        $files = $query->get();
        
        $folderCount = $files->where('type', 'folder')->count();
        $fileCount = $files->where('type', 'file')->count();
        $totalSize = $files->where('type', 'file')->sum('size');
        
        return [
            'folder_count' => $folderCount,
            'file_count' => $fileCount,
            'total_files' => $folderCount + $fileCount,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
        ];
    }

    /**
     * Format bytes to human readable format.
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $factor = floor(log($bytes, 1024));
        
        return round($bytes / (1024 ** $factor), $precision) . ' ' . $units[$factor];
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

        // Check if file is too large for preview
        $maxPreviewSize = 1024 * 1024; // 1MB limit for preview
        $isTruncated = false;
        $previewSize = $file->size;

        if ($file->size > $maxPreviewSize) {
            $previewSize = $maxPreviewSize;
            $isTruncated = true;
        }

        // Check if file type is previewable
        if (!$this->isPreviewableFile($file)) {
            throw new Exception('File type is not previewable. Supported types: text, code, config files.');
        }

        try {
            // Get file content from storage
            $content = Storage::get($file->file_ref);
            
            // If file is too large, truncate it
            if ($isTruncated) {
                $content = substr($content, 0, $maxPreviewSize);
                $content .= "\n\n... [Content truncated - Download full file to see complete content] ...";
            }

            // Detect encoding and validate text
            $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'ASCII'], true);
            $isText = $encoding !== false && $this->isTextContent($content);

            if (!$isText) {
                throw new Exception('File appears to be binary and cannot be previewed as text.');
            }

            // Convert to UTF-8 if needed
            if ($encoding && $encoding !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            }

            // Count lines
            $lineCount = substr_count($content, "\n") + 1;

            return [
                'content' => $content,
                'is_text' => $isText,
                'encoding' => $encoding ?: 'UTF-8',
                'line_count' => $lineCount,
                'is_truncated' => $isTruncated,
                'preview_size' => strlen($content),
            ];

        } catch (\Exception $e) {
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

        // Check by MIME type
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

        // Check common files without extensions
        $commonTextFiles = ['readme', 'license', 'changelog', 'authors', 'dockerfile', 'makefile'];
        if (in_array(strtolower($file->name), $commonTextFiles)) {
            return true;
        }

        return false;
    }

    /**
     * Check if content appears to be text (not binary).
     */
    private function isTextContent(string $content): bool
    {
        // Check for null bytes (common in binary files)
        if (strpos($content, "\0") !== false) {
            return false;
        }

        // Check if most characters are printable
        $printableChars = 0;
        $totalChars = strlen($content);
        
        if ($totalChars === 0) {
            return true; // Empty file is considered text
        }

        for ($i = 0; $i < min($totalChars, 1000); $i++) { // Check first 1000 chars
            $char = ord($content[$i]);
            // Printable ASCII (32-126) + common whitespace (9, 10, 13)
            if (($char >= 32 && $char <= 126) || in_array($char, [9, 10, 13])) {
                $printableChars++;
            }
        }

        $printableRatio = $printableChars / min($totalChars, 1000);
        
        // Consider it text if 90% or more characters are printable
        return $printableRatio >= 0.9;
    }

    /**
     * Download ZIP file temporarily for processing.
     */
    private function downloadZipTemporarily(Repository $repository): string
    {
        $tempPath = storage_path('app/temp/' . Str::uuid() . '.zip');
        
        // Ensure temp directory exists
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        
        // Download from storage
        $zipContent = Storage::get($repository->file_ref);
        file_put_contents($tempPath, $zipContent);
        
        return $tempPath;
    }

    /**
     * Extract ZIP file securely with validation.
     */
    private function extractZipSecurely(string $zipPath, Repository $repository): array
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath);
        
        if ($result !== true) {
            throw new Exception('Failed to open ZIP file: ' . $this->getZipError($result));
        }
        
        $fileStructure = [];
        $totalSize = 0;
        $extractPath = storage_path('app/temp/' . Str::uuid());
        
        // Create extraction directory
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
                
                // Skip system/hidden files
                if ($this->isSystemFile($filename)) {
                    continue;
                }
                
                // Security validations
                $this->validateFilePath($filename);
                $this->validateFileSize($stat['size']);
                
                $totalSize += $stat['size'];
                $maxTotalSize = $this->getConfig('max_total_extracted_size');
                $maxTotalSizeLabel = $this->getSizeLabel('max_total_extracted_size');
                
                if ($totalSize > $maxTotalSize) {
                    throw new Exception("Total extracted size exceeds limit of {$maxTotalSizeLabel}.");
                }
                
                // Determine if it's a folder or file
                $isFolder = substr($filename, -1) === '/';
                
                if ($isFolder) {
                    $fileStructure[] = [
                        'name' => basename(rtrim($filename, '/')),
                        'path' => rtrim($filename, '/'),
                        'type' => 'folder',
                        'size' => null,
                        'mime_type' => null,
                        'parent_path' => $this->getParentPath($filename),
                        'file_ref' => null,
                    ];
                } else {
                    // Extract individual file
                    $extractedFilePath = $extractPath . '/' . $filename;
                    
                    // Ensure directory exists
                    $dir = dirname($extractedFilePath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    
                    // Extract file
                    if (!$zip->extractTo($extractPath, $filename)) {
                        throw new Exception('Failed to extract file: ' . $filename);
                    }
                    
                    // Validate file extension
                    $this->validateFileExtension($filename);
                    
                    // Get MIME type
                    $mimeType = $this->getMimeType($extractedFilePath);
                    
                    $fileStructure[] = [
                        'name' => basename($filename),
                        'path' => $filename,
                        'type' => 'file',
                        'size' => $stat['size'],
                        'mime_type' => $mimeType,
                        'parent_path' => $this->getParentPath($filename),
                        'file_ref' => null, // Will be set when uploaded to Wasabi
                        'local_path' => $extractedFilePath,
                    ];
                }
            }
            
            $zip->close();
            
            // Create missing parent folders
            $fileStructure = $this->ensureParentFolders($fileStructure);
            
            return $fileStructure;
            
        } catch (Exception $e) {
            $zip->close();
            $this->cleanupTemporaryFiles($extractPath);
            throw $e;
        }
    }

    /**
     * Check if file is a system/hidden file that should be skipped.
     */
    private function isSystemFile(string $filename): bool
    {
        // Check if filtering is enabled
        if (!config('filesystem_limits.system_file_filtering.enabled', true)) {
            return false;
        }

        // Get just the filename and directory path
        $basename = basename($filename);
        $directory = dirname($filename);
        
        // Check blocked directories first (skip entire directories)
        $blockedDirs = config('filesystem_limits.system_file_filtering.blocked_directories', []);
        foreach ($blockedDirs as $blockedDir) {
            if (strpos($filename, $blockedDir . '/') === 0 || $directory === $blockedDir) {
                return true;
            }
        }
        
        // Check exact filename matches (case insensitive)
        $exactMatches = config('filesystem_limits.system_file_filtering.exact_matches', []);
        foreach ($exactMatches as $systemFile) {
            if (strcasecmp($basename, $systemFile) === 0) {
                return true;
            }
        }
        
        // Check file extension filters
        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        $blockedExtensions = config('filesystem_limits.system_file_filtering.blocked_extensions', []);
        if ($extension && in_array($extension, $blockedExtensions)) {
            return true;
        }
        
        // Check regex patterns
        $patterns = config('filesystem_limits.system_file_filtering.regex_patterns', []);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }
        
        // Handle dot files (files starting with .)
        if (strpos($basename, '.') === 0) {
            $allowedDotFiles = config('filesystem_limits.system_file_filtering.allowed_dot_files', []);
            
            // Convert to lowercase for comparison
            $allowedDotFilesLower = array_map('strtolower', $allowedDotFiles);
            
            if (!in_array(strtolower($basename), $allowedDotFilesLower)) {
                return true; // Filter out unknown dot files
            }
        }
        
        return false;
    }

    /**
     * Validate file path for security (prevent ZIP slip attacks).
     */
    private function validateFilePath(string $path): void
    {
        // Check for directory traversal attempts
        if (strpos($path, '..') !== false) {
            throw new Exception('Invalid file path: directory traversal detected.');
        }
        
        // Check for absolute paths
        if (strpos($path, '/') === 0 || strpos($path, '\\') === 0) {
            throw new Exception('Invalid file path: absolute paths not allowed.');
        }
        
        // Check for Windows drive letters
        if (preg_match('/^[a-zA-Z]:/', $path)) {
            throw new Exception('Invalid file path: drive letters not allowed.');
        }
        
        // Check path length
        if (strlen($path) > 255) {
            throw new Exception('File path too long.');
        }
    }

    /**
     * Validate file size.
     */
    private function validateFileSize(int $size): void
    {
        $maxSize = $this->getConfig('max_individual_file_size');
        $maxSizeLabel = $this->getSizeLabel('max_individual_file_size');
        
        if ($size > $maxSize) {
            throw new Exception("File size exceeds maximum allowed size of {$maxSizeLabel}.");
        }
    }

    /**
     * Validate file extension.
     */
    private function validateFileExtension(string $filename): void
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = $this->getAllowedExtensions();
        
        if (!empty($extension) && !in_array($extension, $allowedExtensions)) {
            throw new Exception("File type not allowed: {$extension}. Allowed types for {$this->contentType}: " . implode(', ', array_slice($allowedExtensions, 0, 10)) . (count($allowedExtensions) > 10 ? '...' : ''));
        }
    }

    /**
     * Get parent path from file path.
     */
    private function getParentPath(string $path): ?string
    {
        $path = rtrim($path, '/');
        $parentPath = dirname($path);
        
        return ($parentPath === '.' || $parentPath === '') ? null : $parentPath;
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
                    'parent_path' => $this->getParentPath($parentPath),
                    'file_ref' => null,
                ];
                
                $existingPaths[] = $parentPath;
                $parentPath = $this->getParentPath($parentPath);
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
     * Upload individual files to Wasabi.
     */
    private function uploadIndividualFiles(Repository $repository, array $fileStructure, string $tempZipPath): void
    {
        foreach ($fileStructure as $item) {
            if ($item['type'] === 'file' && isset($item['local_path'])) {
                $fileRef = $this->generateIndividualFileRef($repository, $item['path']);
                
                // Upload to storage
                Storage::putFileAs(
                    dirname($fileRef),
                    new \Illuminate\Http\File($item['local_path']),
                    basename($fileRef),
                    'private'
                );
                
                // Update database record
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

    /**
     * Get MIME type of file.
     */
    private function getMimeType(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filePath) ?: null;
    }

    /**
     * Clean up temporary files.
     */
    private function cleanupTemporaryFiles(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            $this->deleteDirectory($path);
        }
    }

    /**
     * Recursively delete directory.
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }

    /**
     * Get ZIP error message.
     */
    private function getZipError(int $code): string
    {
        switch ($code) {
            case ZipArchive::ER_OK: return 'No error';
            case ZipArchive::ER_MULTIDISK: return 'Multi-disk zip archives not supported';
            case ZipArchive::ER_RENAME: return 'Renaming temporary file failed';
            case ZipArchive::ER_CLOSE: return 'Closing zip archive failed';
            case ZipArchive::ER_SEEK: return 'Seek error';
            case ZipArchive::ER_READ: return 'Read error';
            case ZipArchive::ER_WRITE: return 'Write error';
            case ZipArchive::ER_CRC: return 'CRC error';
            case ZipArchive::ER_ZIPCLOSED: return 'Containing zip archive was closed';
            case ZipArchive::ER_NOENT: return 'No such file';
            case ZipArchive::ER_EXISTS: return 'File already exists';
            case ZipArchive::ER_OPEN: return 'Can\'t open file';
            case ZipArchive::ER_TMPOPEN: return 'Failure to create temporary file';
            case ZipArchive::ER_ZLIB: return 'Zlib error';
            case ZipArchive::ER_MEMORY: return 'Memory allocation failure';
            case ZipArchive::ER_CHANGED: return 'Entry has been changed';
            case ZipArchive::ER_COMPNOTSUPP: return 'Compression method not supported';
            case ZipArchive::ER_EOF: return 'Premature EOF';
            case ZipArchive::ER_INVAL: return 'Invalid argument';
            case ZipArchive::ER_NOZIP: return 'Not a zip archive';
            case ZipArchive::ER_INTERNAL: return 'Internal error';
            case ZipArchive::ER_INCONS: return 'Zip archive inconsistent';
            case ZipArchive::ER_REMOVE: return 'Can\'t remove file';
            case ZipArchive::ER_DELETED: return 'Entry has been deleted';
            default: return 'Unknown error code: ' . $code;
        }
    }
}
