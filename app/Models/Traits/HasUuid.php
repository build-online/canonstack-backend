<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuid
{
    /**
     *
     * @return void
     */
    public static function bootHasUuid(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     *
     * @param string $uuid
     * @return Model
     */
    public static function findByUuid(string $uuid): Model
    {
        return static::where('uuid', $uuid)->firstOrFail();
    }
}
