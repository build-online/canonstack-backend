<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasUuid;

class Category extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
    ];

    /**
     * Get the repositories for this category.
     */
    public function repositories()
    {
        return $this->hasMany(Repository::class);
    }
}
