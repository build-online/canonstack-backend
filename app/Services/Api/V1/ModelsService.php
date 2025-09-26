<?php

namespace App\Services\Api\V1;

use App\Models\Repository;
use App\Models\ModelRepository;
use App\Models\Category;
use App\Models\Tag;
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
    private RepositoryService $repositoryService;

    public function __construct(FileSystemService $fileSystemService, RepositoryService $repositoryService)
    {
        $this->fileSystemService = $fileSystemService;
        $this->repositoryService = $repositoryService;
    }
    /**
     * Upload and process a model ZIP file.
     */
    public function uploadModel(array $data, UploadedFile $zipFile): ModelRepository
    {
        DB::beginTransaction();
        
        try {
            $this->fileSystemService->validateZipFile($zipFile);

            // Upload file to storage
            $fileRef = $this->generateFileReference($zipFile);
            $this->uploadToStorage($zipFile, $fileRef);
            
            // Create database records
            $repository = $this->createRepository($data, $fileRef);            
            $model = $this->createModel($repository);
            $this->attachTags($repository, $data['tag_uuids']);
            
            // Extract ZIP contents and store file structure
            $this->fileSystemService->extractAndStoreZipContents($repository);
            
            DB::commit();
            return $model->fresh([
                'repository' => function ($query) {
                    $query->withCount(['downloads', 'likes', 'comments']);
                },
                'repository.user', 
                'repository.category', 
                'repository.tags'
            ]);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            if (isset($fileRef)) {
                Storage::delete($fileRef);
            }
            
            throw $e;
        }
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
            'category_id' => $this->getCategoryIdByUuid($data['category_uuid']),
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
    private function attachTags(Repository $repository, array $tagUuids): void
    {
        $tagIds = $this->getTagIdsByUuids($tagUuids);
        $repository->tags()->attach($tagIds);
    }

    /**
     * Delete a model and all associated files.
     */
    public function deleteModel(ModelRepository $model): void
    {
        $this->repositoryService->deleteRepository($model->repository);
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
                $this->fileSystemService->validateZipFile($zipFile);
                $oldFileRef = $repository->file_ref;
                
                $newFileRef = $this->generateFileReference($zipFile);
                $this->uploadToStorage($zipFile, $newFileRef);
                
                $this->repositoryService->deleteAttachedFiles($repository);
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
            
            if (isset($data['tag_uuids'])) {
                $this->updateTags($repository, $data['tag_uuids']);
            }
            
            DB::commit();
            
            return $model->fresh([
                'repository' => function ($query) {
                    $query->withCount(['downloads', 'likes', 'comments']);
                },
                'repository.user', 
                'repository.category', 
                'repository.tags'
            ]);
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
        $allowedFields = ['name', 'description', 'file_ref', 'status'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        // Handle UUID-based fields
        if (isset($data['category_uuid'])) {
            $updateData['category_id'] = $this->getCategoryIdByUuid($data['category_uuid']);
        }
        
        
        if (!empty($updateData)) {
            $repository->update($updateData);
        }
    }

    /**
     * Update repository tags.
     */
    private function updateTags(Repository $repository, array $tagUuids): void
    {
        $tagIds = $this->getTagIdsByUuids($tagUuids);
        $repository->tags()->sync($tagIds);
    }

    /**
     * Get paginated list of models with filtering options.
     */
    public function getModels(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = ModelRepository::query()
            ->with([
                'repository' => function ($query) {
                    $query->withCount(['downloads', 'likes', 'comments']);
                },
                'repository.user:id,uuid,name,username,email,role', 
                'repository.category:id,uuid,name', 
                'repository.tags:id,uuid,name',
                'repository.files:id,repository_id,type,size'
            ]);

        // Apply filters
        $this->applyModelFilters($query, $filters);

        // Apply sorting
        $this->applyModelSorting($query, $filters);

        // Return paginated results
        $models = $query->paginate(
            $filters['per_page'],
            ['*'],
            'page',
            $filters['page']
        );

        $models->getCollection()->transform(function ($model) {
            if ($model->repository) {
                $stats = $this->repositoryService->getRepositoryStats($model->repository);
                $model->repository->setAttribute('stats', $stats);
            }
            return $model;
        });

        return $models;
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

    /**
     * Get category ID by UUID.
     */
    private function getCategoryIdByUuid(string $uuid): int
    {
        return Category::where('uuid', $uuid)->first()->id;
    }


    /**
     * Get tag IDs by UUIDs.
     */
    private function getTagIdsByUuids(array $uuids): array
    {
        return Tag::whereIn('uuid', $uuids)->get()->pluck('id')->toArray();
    }
}
