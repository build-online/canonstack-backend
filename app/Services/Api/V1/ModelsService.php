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

    /**
     * Get paginated list of models with filtering options.
     */
    public function getModels(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = ModelRepository::query()
            ->with([
                'repository', 
                'repository.user:id,uuid,name,username,email,role', 
                'repository.category:id,uuid,name', 
                'repository.religiousMovement:id,uuid,main_religion,branch', 
                'repository.tags:id,uuid,name'
            ]);

        // Apply filters
        $this->applyModelFilters($query, $filters);

        // Apply sorting
        $this->applyModelSorting($query, $filters);

        // Return paginated results
        return $query->paginate(
            $filters['per_page'],
            ['*'],
            'page',
            $filters['page']
        );
    }

    /**
     * Apply filters to the model query.
     */
    private function applyModelFilters($query, array $filters): void
    {
        // Filter by category UUID
        if ($filters['category_uuid']) {
            $query->whereHas('repository.category', function ($q) use ($filters) {
                $q->where('uuid', $filters['category_uuid']);
            });
        }

        // Filter by single tag UUID
        if ($filters['tag_uuid']) {
            $query->whereHas('repository.tags', function ($q) use ($filters) {
                $q->where('tags.uuid', $filters['tag_uuid']);
            });
        }

        // Filter by multiple tag UUIDs (models that have ALL specified tags)
        if ($filters['tag_uuids'] && is_array($filters['tag_uuids'])) {
            foreach ($filters['tag_uuids'] as $tagUuid) {
                $query->whereHas('repository.tags', function ($q) use ($tagUuid) {
                    $q->where('tags.uuid', $tagUuid);
                });
            }
        }

        // Filter by user UUID (creator)
        if ($filters['user_uuid']) {
            $query->whereHas('repository.user', function ($q) use ($filters) {
                $q->where('uuid', $filters['user_uuid']);
            });
        }

        // Filter by religious movement UUID
        if ($filters['religious_movement_uuid']) {
            $query->whereHas('repository.religiousMovement', function ($q) use ($filters) {
                $q->where('uuid', $filters['religious_movement_uuid']);
            });
        }

        // Filter by status
        if ($filters['status']) {
            $query->whereHas('repository', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        // Filter by name (contains search)
        if ($filters['name']) {
            $query->whereHas('repository', function ($q) use ($filters) {
                $q->where('name', 'LIKE', '%' . $filters['name'] . '%');
            });
        }
    }

    /**
     * Apply sorting to the model query.
     */
    private function applyModelSorting($query, array $filters): void
    {
        $sortBy = $filters['sort_by'];
        $sortDirection = $filters['sort_direction'];

        if (in_array($sortBy, ['name', 'status'])) {
            // Sort by repository fields
            $query->join('repositories', 'models.repository_id', '=', 'repositories.id')
                  ->orderBy("repositories.{$sortBy}", $sortDirection)
                  ->select('models.*'); // Select only model fields to avoid conflicts
        } else {
            // Sort by model fields (created_at, updated_at)
            $query->orderBy("models.{$sortBy}", $sortDirection);
        }
    }
}
