<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChangeRequest extends Model
{
    protected $fillable = [
        'project_id', 'code', 'title', 'description', 'justification',
        'requested_by', 'cost_impact', 'schedule_impact_days',
        'status', 'decision_notes', 'decided_by', 'decided_at', 'applied_at',
        'budget_before', 'budget_after', 'deadline_before', 'deadline_after',
    ];

    protected $casts = [
        'cost_impact'          => 'decimal:2',
        'schedule_impact_days' => 'integer',
        'decided_at'           => 'datetime',
        'applied_at'           => 'datetime',
        'budget_before'        => 'decimal:2',
        'budget_after'         => 'decimal:2',
        'deadline_before'      => 'date',
        'deadline_after'       => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Requirements this change request adds or changes (pivot table). */
    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'change_request_requirements');
    }

    /** Requirements that originated from this change request. */
    public function originatedRequirements(): HasMany
    {
        return $this->hasMany(Requirement::class, 'origin_change_request_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereIn('status', ['approved', 'implemented']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }
}
