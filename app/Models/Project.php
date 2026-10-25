<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    protected $fillable = [
        'name', 'description', 'objectives', 'scope_boundaries',
        'approved_budget', 'current_budget',
        'start_date', 'approved_deadline', 'current_deadline',
        'status', 'charter_approved', 'charter_approved_at',
        'closure_date', 'closure_summary', 'lessons_learned', 'created_by',
    ];

    protected $casts = [
        'approved_budget'     => 'decimal:2',
        'current_budget'      => 'decimal:2',
        'start_date'          => 'date',
        'approved_deadline'   => 'date',
        'current_deadline'    => 'date',
        'charter_approved'    => 'boolean',
        'charter_approved_at' => 'datetime',
        'closure_date'        => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function plan(): HasOne
    {
        return $this->hasOne(ProjectPlan::class);
    }

    public function stakeholders(): HasMany
    {
        return $this->hasMany(Stakeholder::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    public function wbsItems(): HasMany
    {
        return $this->hasMany(WbsItem::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function costEntries(): HasMany
    {
        return $this->hasMany(CostEntry::class);
    }

    public function evmSnapshots(): HasMany
    {
        return $this->hasMany(EvmSnapshot::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }

    public function qualityChecklists(): HasMany
    {
        return $this->hasMany(QualityChecklist::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
