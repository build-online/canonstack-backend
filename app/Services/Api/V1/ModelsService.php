<?php

namespace App\Services\Api\V1;

use App\Models\Repository;
use App\Models\ModelRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;
use Exception;

class ModelsService
{
    private FileSystemService $fileSystemService;

    public function __construct(FileSystemService $fileSystemService)
    {
        $this->fileSystemService = $fileSystemService;
    }
    /**
     * Upload and process a model ZIP file.
     */
    public function uploadModel(array $data, UploadedFile $zipFile): ModelRepository
    {
        DB::beginTransaction();
        
        try {
            // Validate ZIP file
            $this->validateZipFile($zipFile);
            
            // Generate unique file reference
            $fileRef = $this->generateFileReference($zipFile);
            
            // Upload to storage
            $this->uploadToStorage($zipFile, $fileRef);
            
            // Create repository record
            $repository = $this->createRepository($data, $fileRef);
            
            // Create model record
            $model = $this->createModel($repository);
            
            // Attach tags
            $this->attachTags($repository, $data['tag_ids']);
            
            // Extract ZIP contents and store file structure
            $this->fileSystemService->extractAndStoreZipContents($repository);
            
            DB::commit();
            
            return $model->load(['repository.user', 'repository.category', 'repository.religiousMovement', 'repository.tags', 'repository.files']);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded file if it exists
            if (isset($fileRef)) {
                Storage::delete($fileRef);
            }
            
            throw $e;
        }
    }
    
    /**
     * Validate that the uploaded file is a valid ZIP archive.
     */
    private function validateZipFile(UploadedFile $file): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($file->getRealPath());
        
        if (!$result) {
            throw new Exception('Invalid ZIP file or corrupted archive.');
        }
        
        // Check if ZIP has content
        if ($zip->numFiles === 0) {
            $zip->close();
            throw new Exception('ZIP file is empty.');
        }
        
        $zip->close();
    }
    
    /**
     * Generate a unique file reference for storage.
     */
    private function generateFileReference(UploadedFile $file): string
    {
        $timestamp = now()->format('Y-m-d');
        $uuid = Str::uuid();
        $extension = $file->getClientOriginalExtension();
        
        return "models/{$timestamp}/{$uuid}.{$extension}";
    }
    
    /**
     * Upload file to default storage (Wasabi).
     */
    private function uploadToStorage(UploadedFile $file, string $fileRef): void
    {
        $uploaded = Storage::putFileAs(
            dirname($fileRef),
            $file,
            basename($fileRef),
            'private'
        );
        
        if (!$uploaded) {
            throw new Exception('Failed to upload file to storage.');
        }
    }
    
    /**
     * Create the repository database record.
     */
    private function createRepository(array $data, string $fileRef): Repository
    {
        return Repository::create([
            'user_id' => auth()->id(),
            'name' => $data['name'],
            'description' => $data['description'],
            'file_ref' => $fileRef,
            'category_id' => $data['category_id'],
            'religious_movement_id' => $data['religious_movement_id'],
            'status' => 'PENDING_REVIEW',
        ]);
    }
    
    /**
     * Create the model record for this repository.
     */
    private function createModel(Repository $repository): ModelRepository
    {
        return ModelRepository::create([
            'repository_id' => $repository->id,
        ]);
    }
    
    /**
     * Attach tags to the repository.
     */
    private function attachTags(Repository $repository, array $tagIds): void
    {
        $repository->tags()->attach($tagIds);
    }
}
