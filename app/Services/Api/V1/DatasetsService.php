<?php

namespace App\Services\Api\V1;

use App\Models\Dataset;
use App\Models\Repository;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class DatasetsService
{
    private FileSystemService $fileSystemService;
    private RepositoryService $repositoryService;

    public function __construct(FileSystemService $fileSystemService, RepositoryService $repositoryService)
    {
        $this->fileSystemService = $fileSystemService;
        $this->fileSystemService->setContentType('datasets');
        $this->repositoryService = $repositoryService;
    }

    /**
     * Upload and process a dataset ZIP file.
     */
    public function uploadDataset(array $data, UploadedFile $zipFile): Dataset
    {
        DB::beginTransaction();

        try {
            $this->fileSystemService->validateZipFile($zipFile);
            
            // Generate file reference and upload to storage
            $fileRef = $this->generateFileReference($zipFile);
            $this->uploadToStorage($zipFile, $fileRef);
            
            // Create repository
            $repository = $this->createRepository($data, $fileRef);
            $dataset = $this->createDataset($repository);
            $this->attachTags($repository, $data['tag_uuids']);
            
            // Extract and store ZIP contents
            $this->fileSystemService->extractAndStoreZipContents($repository);
            
            DB::commit();
            
            return $dataset->fresh([
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
        
        return "datasets/{$timestamp}/{$uuid}.{$extension}";
    }
    
    /**
     * Upload file to default storage (Wasabi).
     */
    private function uploadToStorage(UploadedFile $file, string $fileRef): void
    {
        Storage::putFileAs(
            dirname($fileRef),
            $file,
            basename($fileRef)
        );
    }

    /**
     * Create repository for the dataset.
     */
    private function createRepository(array $data, string $fileRef): Repository
    {
        return Repository::create([
            'uuid' => Str::uuid(),
            'user_id' => auth()->id(),
            'category_id' => $this->getCategoryIdByUuid($data['category_uuid']),
            'name' => $data['name'],
            'description' => $data['description'],
            'file_ref' => $fileRef,
            'status' => 'PENDING_REVIEW',
        ]);
    }

     /**
     * Create the model record for this repository.
     */
    private function createDataset(Repository $repository): Dataset
    {
        return Dataset::create([
            'repository_id' => $repository->id,
        ]);
    }

    /**
     * Attach tags to repository.
     */
    private function attachTags(Repository $repository, array $tagUuids): void
    {
        $tagIds = $this->getTagIdsByUuids($tagUuids);
        $repository->tags()->attach($tagIds);
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
        return Tag::whereIn('uuid', $uuids)->pluck('id')->toArray();
    }

    /**
     * Get paginated list of datasets with filtering options.
     */
    public function getDatasets(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Dataset::query()
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
        $this->applyDatasetFilters($query, $filters);

        // Apply sorting
        $this->applyDatasetSorting($query, $filters);

        // Return paginated results
        $datasets = $query->paginate(
            $filters['per_page'],
            ['*'],
            'page',
            $filters['page']
        );

        $datasets->getCollection()->transform(function ($dataset) {
            if ($dataset->repository) {
                $stats = $this->repositoryService->getRepositoryStats($dataset->repository);
                $dataset->repository->setAttribute('stats', $stats);
            }
            return $dataset;
        });

        return $datasets;
    }

    /**
     * Apply filters to the dataset query.
     */
    private function applyDatasetFilters($query, array $filters): void
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

        // Filter by multiple tag UUIDs (datasets that have ALL specified tags)
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
     * Apply sorting to the dataset query.
     */
    private function applyDatasetSorting($query, array $filters): void
    {
        $sortBy = $filters['sort_by'];
        $sortDirection = $filters['sort_direction'];

        if (in_array($sortBy, ['name', 'status'])) {
            // Sort by repository fields
            $query->join('repositories', 'datasets.repository_id', '=', 'repositories.id')
                  ->orderBy("repositories.{$sortBy}", $sortDirection)
                  ->select('datasets.*'); // Select only dataset fields to avoid conflicts
        } else {
            // Sort by dataset fields (created_at, updated_at)
            $query->orderBy("datasets.{$sortBy}", $sortDirection);
        }
    }

    /**
     * Delete a dataset and all associated files.
     */
    public function deleteDataset(Dataset $dataset): void
    {
        $this->repositoryService->deleteRepository($dataset->repository);
    }

    /**
     * Update a dataset with new information and optionally replace the ZIP file.
     */
    public function updateDataset(Dataset $dataset, array $data, ?UploadedFile $zipFile = null): Dataset
    {
        DB::beginTransaction();
        
        try {
            $repository = $dataset->repository;
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
            
            return $dataset->fresh([
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
     * Update tags for a repository.
     */
    private function updateTags(Repository $repository, array $tagUuids): void
    {
        $tagIds = $this->getTagIdsByUuids($tagUuids);
        $repository->tags()->sync($tagIds);
    }
}
