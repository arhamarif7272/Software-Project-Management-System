<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityChecklist extends Model
{
    protected $fillable = [
        'project_id', 'wbs_item_id', 'name',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The deliverable this checklist verifies. */
    public function wbsItem(): BelongsTo
    {
        return $this->belongsTo(WbsItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'checklist_id')->orderBy('sort_order');
    }
}
