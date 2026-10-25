<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Defect extends Model
{
    protected $fillable = [
        'project_id', 'code', 'title', 'description',
        'severity', 'status',
        'requirement_id', 'activity_id', 'assigned_to', 'reported_by',
        'reported_on', 'resolved_on', 'resolution_notes',
    ];

    protected $casts = [
        'reported_on' => 'date',
        'resolved_on' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'assigned_to');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'reported_by');
    }

    public function inspectionResults(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }

    /** Defects that are still open, being worked on, or reopened. */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_progress', 'reopened']);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function isUnresolved(): bool
    {
        return in_array($this->status, ['open', 'in_progress', 'reopened'], true);
    }
}
