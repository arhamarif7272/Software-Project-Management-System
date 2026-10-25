<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    // The table only has created_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'project_id', 'type', 'severity', 'message', 'is_resolved', 'resolved_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }
}
