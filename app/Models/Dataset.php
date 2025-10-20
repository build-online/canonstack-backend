<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Get all embeddings for this dataset.
     */
    public function embeddings(): HasMany
    {
        return $this->hasMany(DatasetEmbedding::class);
    }

    /**
     * Get the production embedding (non-experimental).
     * Kept for backward compatibility.
     */
    public function embedding(): HasOne
    {
        return $this->hasOne(DatasetEmbedding::class)->whereNull('variant');
    }

    /**
     * Get embedding by variant (simple or complex).
     */
    public function getEmbeddingByVariant(string $variant): ?DatasetEmbedding
    {
        return $this->embeddings()->where('variant', $variant)->first();
    }
}
