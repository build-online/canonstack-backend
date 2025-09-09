<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasUuid;

class ReligiousMovement extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'main_religion',
        'branch',
    ];

    /**
     * Get the repositories for this religious movement.
     */
    public function repositories()
    {
        return $this->hasMany(Repository::class);
    }
}
