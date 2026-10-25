<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChecklistItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'checklist_id', 'description', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(QualityChecklist::class, 'checklist_id');
    }

    public function inspectionResults(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }

    /** Most recent inspection or test result for this item. */
    public function latestResult(): HasOne
    {
        return $this->hasOne(InspectionResult::class)->latestOfMany('inspected_on');
    }
}
