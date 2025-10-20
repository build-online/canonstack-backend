<?php

namespace App\Services\Api\V1;

use App\Models\Dataset;
use App\Models\RepositoryFile;
use App\Utils\FileSystemUtils;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class JsonlAnalyzerService
{
    /**
     * Analyze JSONL files in a dataset and return field structure.
     *
     * @param Dataset $dataset
     * @return array|null Returns analysis data or null if no suitable JSONL files found
     */
    public function analyzeDatasetJsonl(Dataset $dataset): ?array
    {
        $allFiles = $dataset->repository->files;
        
        Log::info("Analyzing dataset for JSONL files", [
            'dataset_id' => $dataset->id,
            'total_files' => $allFiles->count()
        ]);
        
        // Find ALL JSONL files by extension only (don't download yet)
        $jsonlFiles = $allFiles->filter(function ($file) {
            $isJsonl = str_ends_with(strtolower($file->name), '.jsonl');
            Log::info("Checking file", [
                'file_name' => $file->name,
                'file_type' => $file->type,
                'is_jsonl' => $isJsonl
            ]);
            return $isJsonl;
        });

        Log::info("JSONL files found by extension", [
            'dataset_id' => $dataset->id,
            'jsonl_count' => $jsonlFiles->count(),
            'files_found' => $jsonlFiles->pluck('name')->toArray()
        ]);

        if ($jsonlFiles->isEmpty()) {
            return null;
        }

        // Analyze the first JSONL file (assuming all have same structure)
        $firstFile = $jsonlFiles->first();
        
        try {
            $analysis = $this->analyzeJsonlFile($firstFile);
            
            return [
                'has_jsonl' => true,
                'jsonl_files' => $jsonlFiles->map(fn($f) => [
                    'uuid' => $f->uuid,
                    'name' => $f->name,
                    'size' => $f->size,
                ])->values()->toArray(),
                'fields' => $analysis['fields'],
                'sample_records' => $analysis['sample_records'],
                'total_records_sampled' => $analysis['total_records_sampled'],
            ];
            
        } catch (Exception $e) {
            Log::error("Failed to analyze JSONL file", [
                'file_id' => $firstFile->id,
                'file_name' => $firstFile->name,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Check if a file is a JSONL file (any valid JSONL).
     *
     * @param RepositoryFile $file
     * @return bool
     */
    private function isJsonlFile(RepositoryFile $file): bool
    {
        // Check file extension
        if (!str_ends_with(strtolower($file->name), '.jsonl')) {
            Log::info("File is not JSONL", ['file_name' => $file->name]);
            return false;
        }

        $tempPath = null;
        
        try {
            // Download file temporarily to check format
            $tempPath = $this->downloadFileTemporarily($file);
            
            // Read first line to validate format
            $handle = fopen($tempPath, 'r');
            if (!$handle) {
                Log::warning("Could not open JSONL file", ['file_name' => $file->name]);
                return false;
            }
            
            $firstLine = fgets($handle);
            fclose($handle);
            
            if (!$firstLine) {
                Log::warning("JSONL file is empty", ['file_name' => $file->name]);
                return false;
            }
            
            $data = json_decode(trim($firstLine), true);
            
            if ($data === null) {
                Log::warning("Invalid JSON in JSONL file", ['file_name' => $file->name]);
                return false;
            }
            
            Log::info("JSONL file detected", [
                'file_name' => $file->name,
                'fields' => array_keys($data)
            ]);
            
            // Clean up temporary file
            FileSystemUtils::cleanupTemporaryFiles($tempPath);
            
            // Return true for any valid JSONL file
            return true;
            
        } catch (Exception $e) {
            Log::warning("Failed to check JSONL file", [
                'file_id' => $file->id,
                'file_name' => $file->name,
                'error' => $e->getMessage()
            ]);
            
            // Clean up temp file on error
            if ($tempPath) {
                FileSystemUtils::cleanupTemporaryFiles($tempPath);
            }
            
            return false;
        }
    }

    /**
     * Check if a file is a raw JSONL file (not pre-embedded).
     *
     * @param RepositoryFile $file
     * @return bool
     */
    private function isRawJsonlFile(RepositoryFile $file): bool
    {
        // Check file extension
        if (!str_ends_with(strtolower($file->name), '.jsonl')) {
            Log::info("File is not JSONL", ['file_name' => $file->name]);
            return false;
        }

        $tempPath = null;
        
        try {
            // Download file temporarily to check format
            $tempPath = $this->downloadFileTemporarily($file);
            
            // Read first line to validate format
            $handle = fopen($tempPath, 'r');
            if (!$handle) {
                Log::warning("Could not open JSONL file", ['file_name' => $file->name]);
                return false;
            }
            
            $firstLine = fgets($handle);
            fclose($handle);
            
            if (!$firstLine) {
                Log::warning("JSONL file is empty", ['file_name' => $file->name]);
                return false;
            }
            
            $data = json_decode(trim($firstLine), true);
            
            if ($data === null) {
                Log::warning("Invalid JSON in JSONL file", ['file_name' => $file->name]);
            }
            
            // If it has a 'vector' field with array values, it's pre-embedded
            $hasVector = isset($data['vector']) && is_array($data['vector']) && count($data['vector']) > 0;
            $isPreEmbedded = $data !== null && $hasVector;
            
            Log::info("JSONL file analysis", [
                'file_name' => $file->name,
                'has_data' => $data !== null,
                'has_vector' => $hasVector,
                'is_pre_embedded' => $isPreEmbedded,
                'fields' => $data ? array_keys($data) : []
            ]);
            
            // Clean up temporary file
            FileSystemUtils::cleanupTemporaryFiles($tempPath);
            
            // Return true if it's NOT pre-embedded and has valid JSON structure
            return $data !== null && !$isPreEmbedded;
            
        } catch (Exception $e) {
            Log::warning("Failed to check JSONL file", [
                'file_id' => $file->id,
                'file_name' => $file->name,
                'error' => $e->getMessage()
            ]);
            
            // Clean up temp file on error
            if ($tempPath) {
                FileSystemUtils::cleanupTemporaryFiles($tempPath);
            }
            
            return false;
        }
    }

    /**
     * Analyze a single JSONL file and extract field structure and samples.
     *
     * @param RepositoryFile $file
     * @return array
     */
    private function analyzeJsonlFile(RepositoryFile $file): array
    {
        $startTime = microtime(true);
        Log::info("Starting JSONL file analysis", [
            'file_name' => $file->name,
            'file_size' => $file->size
        ]);
        
        // Only download the first 100KB for analysis (instead of entire file)
        $bytesToRead = 100 * 1024; // 100KB should be enough for first few lines
        $partialContent = $this->downloadFilePartially($file, $bytesToRead);
        
        $downloadTime = microtime(true) - $startTime;
        Log::info("File partially downloaded", [
            'file_name' => $file->name,
            'bytes_downloaded' => strlen($partialContent),
            'download_time_seconds' => round($downloadTime, 2)
        ]);
        
        try {
            $fields = [];
            $sampleRecords = [];
            $maxSamples = 5; // Get first 5 records as samples
            $recordCount = 0;
            $isPreEmbedded = false;
            
            // Split content into lines
            $lines = explode("\n", $partialContent);
            
            $linesRead = 0;
            $maxLinesToRead = min(count($lines), 100); // Safety limit
            
            foreach ($lines as $line) {
                if ($recordCount >= $maxSamples || $linesRead >= $maxLinesToRead) {
                    break;
                }
                
                $linesRead++;
                $line = trim($line);
                
                if (empty($line)) {
                    continue; // Skip empty lines
                }
                
                $data = json_decode($line, true);
                
                if ($data === null) {
                    Log::warning("Invalid JSON on line {$linesRead} in file {$file->name}");
                    continue; // Skip invalid JSON
                }
                
                // Check if this is a pre-embedded JSONL (has vector and payload structure)
                if ($recordCount === 0) {
                    $isPreEmbedded = isset($data['vector']) && isset($data['payload']) && is_array($data['vector']);
                    Log::info("JSONL structure detected", [
                        'file_name' => $file->name,
                        'is_pre_embedded' => $isPreEmbedded,
                        'first_record_keys' => array_keys($data)
                    ]);
                }
                
                // Collect all field names
                // For pre-embedded JSONL: include both root fields (id, vector) AND payload fields
                // For raw JSONL: just use root level fields
                $fieldsToAnalyze = [];
                
                if ($isPreEmbedded) {
                    // Add root-level fields (id, vector)
                    foreach ($data as $key => $value) {
                        if ($key !== 'payload') { // Don't include payload object itself
                            $fieldsToAnalyze[$key] = $value;
                        }
                    }
                    // Add fields from payload
                    if (isset($data['payload']) && is_array($data['payload'])) {
                        foreach ($data['payload'] as $key => $value) {
                            $fieldsToAnalyze[$key] = $value;
                        }
                    }
                } else {
                    // Raw JSONL - use all root level fields
                    $fieldsToAnalyze = $data;
                }
                
                foreach ($fieldsToAnalyze as $key => $value) {
                    if (!isset($fields[$key])) {
                        $fields[$key] = [
                            'name' => $key,
                            'type' => $this->detectFieldType($value),
                            'sample_values' => [],
                        ];
                    }
                    
                    // Add sample value (limit to first 100 chars for display)
                    // For arrays (like vectors), show dimension count instead of full array
                    if (is_array($value)) {
                        $sampleValue = '[Array with ' . count($value) . ' elements]';
                    } elseif (is_string($value)) {
                        $sampleValue = strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value;
                    } else {
                        $sampleValue = $value;
                    }
                    
                    if (count($fields[$key]['sample_values']) < 3) {
                        $fields[$key]['sample_values'][] = $sampleValue;
                    }
                }
                
                // Add to sample records (limit each field value for display)
                // Use the same fieldsToAnalyze array we built above
                $sampleRecord = [];
                foreach ($fieldsToAnalyze as $key => $value) {
                    // For arrays (like vectors), show dimension info instead of full array
                    if (is_array($value)) {
                        $sampleRecord[$key] = '[Array with ' . count($value) . ' elements]';
                    } elseif (is_string($value)) {
                        $sampleRecord[$key] = strlen($value) > 200 ? substr($value, 0, 200) . '...' : $value;
                    } else {
                        $sampleRecord[$key] = $value;
                    }
                }
                $sampleRecords[] = $sampleRecord;
                
                $recordCount++;
            }
            
            $totalTime = microtime(true) - $startTime;
            Log::info("JSONL file analysis completed", [
                'file_name' => $file->name,
                'total_time_seconds' => round($totalTime, 2),
                'records_sampled' => $recordCount,
                'fields_found' => count($fields)
            ]);
            
            return [
                'fields' => array_values($fields),
                'sample_records' => $sampleRecords,
                'total_records_sampled' => $recordCount,
                'is_pre_embedded' => $isPreEmbedded,
            ];
            
        } catch (Exception $e) {
            Log::error("Failed to analyze JSONL file", [
                'file_name' => $file->name,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Download only the first part of a file for analysis using HTTP Range requests.
     *
     * @param RepositoryFile $file
     * @param int $bytes Number of bytes to download
     * @return string
     */
    private function downloadFilePartially(RepositoryFile $file, int $bytes): string
    {
        try {
            // Generate a pre-signed URL for the file (valid for 5 minutes)
            $url = Storage::temporaryUrl($file->file_ref, now()->addMinutes(5));
            
            // Use Guzzle to make a range request (only download first N bytes)
            $client = new \GuzzleHttp\Client();
            $response = $client->get($url, [
                'headers' => [
                    'Range' => "bytes=0-" . ($bytes - 1)
                ],
                'timeout' => 10,
                'connect_timeout' => 5,
            ]);
            
            $content = $response->getBody()->getContents();
            
            Log::info("Successfully fetched partial content via range request", [
                'file_name' => $file->name,
                'bytes_requested' => $bytes,
                'bytes_received' => strlen($content)
            ]);
            
            return $content;
            
        } catch (Exception $e) {
            Log::warning("Failed to use range request, falling back to stream", [
                'file_name' => $file->name,
                'error' => $e->getMessage()
            ]);
            
            // Fallback 1: Try streaming (may still download full file)
            try {
                $stream = Storage::readStream($file->file_ref);
                
                if ($stream) {
                    $content = stream_get_contents($stream, $bytes);
                    fclose($stream);
                    return $content;
                }
            } catch (Exception $e2) {
                Log::warning("Stream also failed", ['error' => $e2->getMessage()]);
            }
            
            // Fallback 2: Download entire file (last resort)
            Log::warning("Downloading entire file as last resort", [
                'file_name' => $file->name
            ]);
            
            $fullContent = Storage::get($file->file_ref);
            return substr($fullContent, 0, $bytes);
        }
    }

    /**
     * Detect the type of a field value.
     *
     * @param mixed $value
     * @return string
     */
    private function detectFieldType($value): string
    {
        if (is_string($value)) {
            return 'string';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_bool($value)) {
            return 'boolean';
        } elseif (is_array($value)) {
            return 'array';
        } elseif (is_object($value)) {
            return 'object';
        } else {
            return 'unknown';
        }
    }

    /**
     * Download a file temporarily for processing.
     *
     * @param RepositoryFile $file
     * @return string Temporary file path
     */
    private function downloadFileTemporarily(RepositoryFile $file): string
    {
        $tempPath = sys_get_temp_dir() . '/' . uniqid('jsonl_') . '_' . $file->name;
        
        $content = Storage::get($file->file_ref);
        file_put_contents($tempPath, $content);
        
        return $tempPath;
    }
}

