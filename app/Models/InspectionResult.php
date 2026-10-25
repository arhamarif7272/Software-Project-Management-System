<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionResult extends Model
{
    // The table only has created_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'checklist_item_id', 'inspected_by', 'inspection_type',
        'result', 'inspected_on', 'notes', 'defect_id',
    ];

    protected $casts = [
        'inspected_on' => 'date',
    ];

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'inspected_by');
    }

    /** Defect raised because this check failed. */
    public function defect(): BelongsTo
    {
        return $this->belongsTo(Defect::class);
    }

    public function hasFailed(): bool
    {
        return $this->result === 'fail';
    }
}
