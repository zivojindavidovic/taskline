<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a time-ordered UUIDv7 surrogate key that is used for all
 * URL/route exposure, so sequential integer primary keys never appear in
 * public URLs (and can't be enumerated). The integer `id` stays the internal
 * key for foreign keys, broadcast channels, and in-memory state matching.
 *
 *  - A `uuid` is generated on create (if not already set).
 *  - Route-model binding resolves by `uuid` (getRouteKeyName).
 *
 * Requires a `uuid` column (unique, indexed) on the model's table.
 */
trait HasUuidv7
{
    protected static function bootHasUuidv7(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
