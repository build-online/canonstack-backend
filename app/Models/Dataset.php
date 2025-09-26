<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasUuid;

class Dataset extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'repository_id',
        'is_featured',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'is_featured' => 'boolean',
    ];

    /**
     * Get the repository that owns the dataset.
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
