<?php

namespace App\Transformers;

use App\Models\RepositoryFile;
use League\Fractal\TransformerAbstract;

class RepositoryFileTransformer extends TransformerAbstract
{
    protected array $availableIncludes = [
        'repository',
        'children',
        'parent'
    ];

    public function transform(RepositoryFile $file): array
    {
        return [
            'uuid' => $file->uuid,
            'repository_id' => $file->repository_id,
            'name' => $file->name,
            'path' => $file->path,
            'type' => $file->type,
            'size' => $file->size,
            'human_size' => $file->human_size,
            'mime_type' => $file->mime_type,
            'parent_path' => $file->parent_path,
            'file_ref' => $file->file_ref,
            'is_file' => $file->isFile(),
            'is_folder' => $file->isFolder(),
            'created_at' => $file->created_at->toISOString(),
            'updated_at' => $file->updated_at->toISOString(),
        ];
    }

    public function includeRepository(RepositoryFile $file)
    {
        if ($file->repository) {
            return $this->item($file->repository, new RepositoryTransformer());
        }
        return $this->null();
    }

    public function includeChildren(RepositoryFile $file)
    {
        if ($file->isFolder()) {
            return $this->collection($file->children, new RepositoryFileTransformer());
        }
        return $this->null();
    }

    public function includeParent(RepositoryFile $file)
    {
        if ($file->parent) {
            return $this->item($file->parent, new RepositoryFileTransformer());
        }
        return $this->null();
    }
}
