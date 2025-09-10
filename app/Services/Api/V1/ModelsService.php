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
use Illuminate\Support\Facades\Log;

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
            $this->validateZipFile($zipFile);

            // Upload file to storage
            $fileRef = $this->generateFileReference($zipFile);
            $this->uploadToStorage($zipFile, $fileRef);
            
            // Create database records
            $repository = $this->createRepository($data, $fileRef);            
            $model = $this->createModel($repository);
            $this->attachTags($repository, $data['tag_ids']);
            
            // Extract ZIP contents and store file structure
            $this->fileSystemService->extractAndStoreZipContents($repository);
            
            DB::commit();
            return $model;
            
        } catch (Exception $e) {
            DB::rollBack();
            
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

    /**
     * Delete a model and all associated files.
     */
    public function deleteModel(ModelRepository $model): void
    {
        DB::beginTransaction();
        
        try {
            $repository = $model->repository;
            
            $this->deleteRepositoryFiles($repository);
            if ($repository->file_ref) {
                Storage::delete($repository->file_ref);
            }
            
            // Cascade deletes will handle models, repository_files, repository_tags
            $repository->delete();

            DB::commit();            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete all files associated with a repository from storage.
     */
    private function deleteRepositoryFiles(Repository $repository): void
    {
        $files = $repository->files()->where('type', 'file')->get();
        
        foreach ($files as $file) {
            if ($file->file_ref) {
                try {
                    Storage::delete($file->file_ref);
                } catch (Exception $e) {
                    Log::warning("Failed to delete file from storage: {$file->file_ref}. Error: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Update a model with new information and optionally replace the ZIP file.
     */
    public function updateModel(ModelRepository $model, array $data, ?UploadedFile $zipFile = null): ModelRepository
    {
        DB::beginTransaction();
        
        try {
            $repository = $model->repository;
            $oldFileRef = null;
            
            if ($zipFile) {
                $this->validateZipFile($zipFile);
                $oldFileRef = $repository->file_ref;
                
                $newFileRef = $this->generateFileReference($zipFile);
                $this->uploadToStorage($zipFile, $newFileRef);
                
                $this->deleteRepositoryFiles($repository);
                $repository->files()->delete();
                
                $data['file_ref'] = $newFileRef;
                $data['status'] = 'PENDING_REVIEW';
                
                // Update repository metadata (must be done before extraction)
                $this->updateRepository($repository, $data);
                
                $this->fileSystemService->extractAndStoreZipContents($repository);
                
                if ($oldFileRef) {
                    Storage::delete($oldFileRef);
                }
            } else {
                $this->updateRepository($repository, $data);
            }
            
            if (isset($data['tag_ids'])) {
                $this->updateTags($repository, $data['tag_ids']);
            }
            
            DB::commit();
            
            return $model->fresh(['repository', 'repository.user', 'repository.category', 'repository.religiousMovement', 'repository.tags']);
        } catch (Exception $e) {
            DB::rollBack();
            
            if (isset($newFileRef)) {
                Storage::delete($newFileRef);
            }
            
            throw $e;
        }
    }

    /**
     * Update repository metadata.
     */
    private function updateRepository(Repository $repository, array $data): void
    {
        $allowedFields = ['name', 'description', 'category_id', 'file_ref', 'status'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        if (!empty($updateData)) {
            $repository->update($updateData);
        }
    }

    /**
     * Update repository tags.
     */
    private function updateTags(Repository $repository, array $tagIds): void
    {
        $repository->tags()->sync($tagIds);
    }
}
