<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WbsItem extends Model
{
    protected $fillable = [
        'project_id', 'parent_id', 'requirement_id',
        'wbs_code', 'name', 'description', 'level_type', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WbsItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WbsItem::class, 'parent_id')->orderBy('sort_order')->orderBy('wbs_code');
    }

    /** Load the full tree below this item: WbsItem::roots()->with('childrenRecursive'). */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function qualityChecklists(): HasMany
    {
        return $this->hasMany(QualityChecklist::class);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->orderBy('sort_order')->orderBy('wbs_code');
    }
}
