<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasUuid;

class Repository extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'description',
        'file_ref',
        'status',
        'category_id',
        'approved_by',
        'religious_movement_id',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the user that owns the repository.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category that the repository belongs to.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the religious movement that the repository belongs to.
     */
    public function religiousMovement()
    {
        return $this->belongsTo(ReligiousMovement::class);
    }

    /**
     * Get the user who approved this repository.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the tags for this repository.
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'repository_tags');
    }

    /**
     * Get the model for this repository.
     */
    public function model()
    {
        return $this->hasOne(ModelRepository::class);
    }

    /**
     * Get all files for this repository.
     */
    public function files()
    {
        return $this->hasMany(RepositoryFile::class);
    }

    /**
     * Get root level files and folders.
     */
    public function rootFiles()
    {
        return $this->hasMany(RepositoryFile::class)->root();
    }

    /**
     * Get all downloads for this repository.
     */
    public function downloads()
    {
        return $this->hasMany(Download::class);
    }
}
