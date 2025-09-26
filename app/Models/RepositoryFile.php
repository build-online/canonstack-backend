<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasUuid;

class RepositoryFile extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'repository_id',
        'name',
        'path',
        'type',
        'size',
        'mime_type',
        'parent_path',
        'file_ref',
    ];

    protected $casts = [
        'size' => 'integer',
        'type' => 'string',
    ];

    /**
     * Get the repository that this file belongs to.
     */
    public function repository()
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Get child files/folders for this folder.
     */
    public function children()
    {
        return $this->hasMany(RepositoryFile::class, 'parent_path', 'path')
                   ->where('repository_id', $this->repository_id);
    }

    /**
     * Get the parent folder of this file/folder.
     */
    public function parent()
    {
        return $this->belongsTo(RepositoryFile::class, 'parent_path', 'path')
                   ->where('repository_id', $this->repository_id);
    }

    /**
     * Scope to get only files.
     */
    public function scopeFiles($query)
    {
        return $query->where('type', 'file');
    }

    /**
     * Scope to get only folders.
     */
    public function scopeFolders($query)
    {
        return $query->where('type', 'folder');
    }

    /**
     * Scope to get root level items (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_path');
    }

    /**
     * Check if this is a file.
     */
    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    /**
     * Check if this is a folder.
     */
    public function isFolder(): bool
    {
        return $this->type === 'folder';
    }

    /**
     * Get human readable file size.
     */
    public function getHumanSizeAttribute(): ?string
    {
        if (!$this->size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->size;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
