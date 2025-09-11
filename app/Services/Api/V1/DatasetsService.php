<?php

namespace App\Services\Api\V1;

use App\Models\Dataset;
use App\Models\Repository;
use App\Models\ReligiousMovement;
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

    public function __construct(FileSystemService $fileSystemService)
    {
        $this->fileSystemService = $fileSystemService;
        $this->fileSystemService->setContentType('datasets');
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
                'repository.religiousMovement',
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
            'religious_movement_id' => $this->getReligiousMovementIdByUuid($data['religious_movement_uuid']),
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
     * Get religious movement ID by UUID.
     */
    private function getReligiousMovementIdByUuid(string $uuid): int
    {
        return ReligiousMovement::where('uuid', $uuid)->first()->id;
    }

    /**
     * Get tag IDs by UUIDs.
     */
    private function getTagIdsByUuids(array $uuids): array
    {
        return Tag::whereIn('uuid', $uuids)->pluck('id')->toArray();
    }
}
