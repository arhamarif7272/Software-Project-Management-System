<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamMember extends Model
{
    protected $fillable = [
        'project_id', 'name', 'role', 'email', 'daily_rate', 'is_active',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'assigned_to');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class, 'responsible_id');
    }

    public function assignedDefects(): HasMany
    {
        return $this->hasMany(Defect::class, 'assigned_to');
    }

    public function reportedDefects(): HasMany
    {
        return $this->hasMany(Defect::class, 'reported_by');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class, 'owner_id');
    }
}
