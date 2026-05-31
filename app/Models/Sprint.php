<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasUuidv7;

class Sprint extends Model
{
    use HasUuidv7;

    protected $fillable = ['project_id', 'name', 'start_date', 'end_date', 'status', 'locked'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'locked'     => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
