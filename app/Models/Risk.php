<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Risk extends Model
{
    /**
     * Note: `score` is a generated column (likelihood x impact) calculated by MySQL,
     * so it is intentionally NOT fillable. Call $risk->refresh() after create/update to read it.
     */
    protected $fillable = [
        'project_id', 'code', 'title', 'description', 'category',
        'likelihood', 'impact', 'level', 'status', 'owner_id',
    ];

    protected $casts = [
        'likelihood' => 'integer',
        'impact'     => 'integer',
        'score'      => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'owner_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(RiskResponse::class);
    }

    /** Top-level PDPC nodes (the potential problems) with their action chains. */
    public function rootResponses(): HasMany
    {
        return $this->hasMany(RiskResponse::class)->whereNull('parent_id')->with('childrenRecursive');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['identified', 'mitigating']);
    }

    /** Highest-scoring risks first (prioritization matrix ranking). */
    public function scopeRanked(Builder $query): Builder
    {
        return $query->orderByDesc('score')->orderByDesc('impact');
    }
}
