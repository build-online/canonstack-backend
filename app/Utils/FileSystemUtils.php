<?php

namespace App\Utils;

use Exception;
use finfo;

class FileSystemUtils
{
    /**
     * Validate file path for security (prevent ZIP slip attacks).
     */
    public static function validateFilePath(string $path): void
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
     * Validate file size against configuration.
     */
    public static function validateFileSize(int $size, string $contentType): void
    {
        $maxSize = config("filesystem_limits.{$contentType}.max_individual_file_size");
        $maxSizeLabel = config("filesystem_limits.size_labels.{$contentType}.max_individual_file_size", 'Unknown');
        
        if ($size > $maxSize) {
            throw new Exception("File size exceeds maximum allowed size of {$maxSizeLabel}.");
        }
    }

    /**
     * Validate file extension against allowed extensions.
     */
    public static function validateFileExtension(string $filename, string $contentType): void
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = config("filesystem_limits.allowed_extensions.{$contentType}", []);
        
        if (!empty($allowedExtensions) && $extension && !in_array($extension, $allowedExtensions)) {
            throw new Exception("File extension '.{$extension}' is not allowed.");
        }
    }

    /**
     * Check if file is a system/hidden file that should be skipped.
     */
    public static function isSystemFile(string $filename): bool
    {
        if (!config('filesystem_limits.system_file_filtering.enabled', true)) {
            return false;
        }

        $basename = basename($filename);
        $directory = dirname($filename);
        
        $blockedDirs = config('filesystem_limits.system_file_filtering.blocked_directories', []);
        foreach ($blockedDirs as $blockedDir) {
            if (strpos($filename, $blockedDir . '/') === 0 || $directory === $blockedDir) {
                return true;
            }
        }
        
        $exactMatches = config('filesystem_limits.system_file_filtering.exact_matches', []);
        foreach ($exactMatches as $systemFile) {
            if (strcasecmp($basename, $systemFile) === 0) {
                return true;
            }
        }
        
        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        $blockedExtensions = config('filesystem_limits.system_file_filtering.blocked_extensions', []);
        if ($extension && in_array($extension, $blockedExtensions)) {
            return true;
        }
        
        $patterns = config('filesystem_limits.system_file_filtering.regex_patterns', []);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }
        
        if (strpos($basename, '.') === 0) {
            $allowedDotFiles = config('filesystem_limits.system_file_filtering.allowed_dot_files', []);
            
            $allowedDotFilesLower = array_map('strtolower', $allowedDotFiles);
            
            if (!in_array(strtolower($basename), $allowedDotFilesLower)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if content appears to be text (not binary).
     */
    public static function isTextContent(string $content): bool
    {
        if (strpos($content, "\0") !== false) {
            return false;
        }

        $printableChars = 0;
        $totalChars = strlen($content);
        
        if ($totalChars === 0) {
            return true;
        }

        for ($i = 0; $i < min($totalChars, 1000); $i++) {
            $char = ord($content[$i]);
            // Printable ASCII (32-126) + common whitespace (9, 10, 13)
            if (($char >= 32 && $char <= 126) || in_array($char, [9, 10, 13])) {
                $printableChars++;
            }
        }

        $printableRatio = $printableChars / min($totalChars, 1000);
        
        return $printableRatio >= 0.9;
    }

    /**
     * Get MIME type of file.
     */
    public static function getMimeType(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filePath) ?: null;
    }

    /**
     * Format bytes into human readable format.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $factor = floor(log($bytes, 1024));
        
        return round($bytes / (1024 ** $factor), $precision) . ' ' . $units[$factor];
    }

    /**
     * Get parent path from file path.
     */
    public static function getParentPath(string $path): ?string
    {
        $parentPath = dirname($path);
        return ($parentPath === '.' || $parentPath === '') ? null : $parentPath;
    }

    /**
     * Clean up temporary files and directories.
     */
    public static function cleanupTemporaryFiles(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            self::deleteDirectory($path);
        }
    }

    /**
     * Recursively delete directory.
     */
    public static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }

    /**
     * Get ZIP error message from error code.
     */
    public static function getZipError(int $code): string
    {
        switch ($code) {
            case \ZipArchive::ER_OK:
                return 'No error';
            case \ZipArchive::ER_MULTIDISK:
                return 'Multi-disk zip archives not supported';
            case \ZipArchive::ER_RENAME:
                return 'Renaming temporary file failed';
            case \ZipArchive::ER_CLOSE:
                return 'Closing zip archive failed';
            case \ZipArchive::ER_SEEK:
                return 'Seek error';
            case \ZipArchive::ER_READ:
                return 'Read error';
            case \ZipArchive::ER_WRITE:
                return 'Write error';
            case \ZipArchive::ER_CRC:
                return 'CRC error';
            case \ZipArchive::ER_ZIPCLOSED:
                return 'Containing zip archive was closed';
            case \ZipArchive::ER_NOENT:
                return 'No such file';
            case \ZipArchive::ER_EXISTS:
                return 'File already exists';
            case \ZipArchive::ER_OPEN:
                return 'Can\'t open file';
            case \ZipArchive::ER_TMPOPEN:
                return 'Failure to create temporary file';
            case \ZipArchive::ER_ZLIB:
                return 'Zlib error';
            case \ZipArchive::ER_MEMORY:
                return 'Memory allocation failure';
            case \ZipArchive::ER_CHANGED:
                return 'Entry has been changed';
            case \ZipArchive::ER_COMPNOTSUPP:
                return 'Compression method not supported';
            case \ZipArchive::ER_EOF:
                return 'Premature EOF';
            case \ZipArchive::ER_INVAL:
                return 'Invalid argument';
            case \ZipArchive::ER_NOZIP:
                return 'Not a zip archive';
            case \ZipArchive::ER_INTERNAL:
                return 'Internal error';
            case \ZipArchive::ER_INCONS:
                return 'Zip archive inconsistent';
            case \ZipArchive::ER_REMOVE:
                return 'Can\'t remove file';
            case \ZipArchive::ER_DELETED:
                return 'Entry has been deleted';
            default:
                return 'Unknown error code: ' . $code;
        }
    }
}
