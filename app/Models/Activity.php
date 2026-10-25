<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $fillable = [
        'project_id', 'wbs_item_id', 'requirement_id', 'assigned_to', 'milestone_id',
        'code', 'name', 'duration_days',
        'planned_start', 'planned_end', 'late_start', 'late_end',
        'slack_days', 'is_critical',
        'actual_start', 'actual_end', 'percent_complete', 'status', 'delay_days',
        'estimated_cost',
    ];

    protected $casts = [
        'duration_days'    => 'integer',
        'planned_start'    => 'date',
        'planned_end'      => 'date',
        'late_start'       => 'date',
        'late_end'         => 'date',
        'slack_days'       => 'integer',
        'is_critical'      => 'boolean',
        'actual_start'     => 'date',
        'actual_end'       => 'date',
        'percent_complete' => 'decimal:2',
        'delay_days'       => 'integer',
        'estimated_cost'   => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function wbsItem(): BelongsTo
    {
        return $this->belongsTo(WbsItem::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'assigned_to');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /** Raw dependency rows where this activity is the successor. */
    public function dependencies(): HasMany
    {
        return $this->hasMany(ActivityDependency::class, 'activity_id');
    }

    /** Activities that must happen before this one. */
    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_dependencies', 'activity_id', 'predecessor_id')
            ->withPivot('dependency_type', 'lag_days');
    }

    /** Activities that depend on this one. */
    public function successors(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_dependencies', 'predecessor_id', 'activity_id')
            ->withPivot('dependency_type', 'lag_days');
    }

    public function costEntries(): HasMany
    {
        return $this->hasMany(CostEntry::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }

    public function scopeDelayed(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', 'delayed')->orWhere('delay_days', '>', 0);
        });
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('is_critical', true);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
