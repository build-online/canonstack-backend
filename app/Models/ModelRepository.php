<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasUuid;

class ModelRepository extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'models';

    protected $fillable = [
        'uuid',
        'repository_id',
    ];

    /**
     * Get the repository that this model belongs to.
     */
    public function repository()
    {
        return $this->belongsTo(Repository::class);
    }
}
