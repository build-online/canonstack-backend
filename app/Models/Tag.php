<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasUuid;

class Tag extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
    ];

    /**
     * Get the repositories that have this tag.
     */
    public function repositories()
    {
        return $this->belongsToMany(Repository::class, 'repository_tags');
    }
}
