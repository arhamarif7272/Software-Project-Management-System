<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requirement extends Model
{
    protected $fillable = [
        'project_id', 'code', 'title', 'description',
        'type', 'priority', 'status', 'in_scope',
        'origin_change_request_id', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'in_scope'    => 'boolean',
        'approved_at' => 'datetime',
    ];

    /**
     * Scope guard: an unapproved requirement can never be placed in the approved scope.
     * (The database CHECK constraint enforces the same rule as a second line of defence.)
     */
    protected static function booted(): void
    {
        static::saving(function (Requirement $requirement) {
            if ($requirement->in_scope && $requirement->status !== 'approved') {
                throw new DomainException(
                    "Requirement {$requirement->code} cannot be in the project scope until it is approved."
                );
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function originChangeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class, 'origin_change_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function changeRequests(): BelongsToMany
    {
        return $this->belongsToMany(ChangeRequest::class, 'change_request_requirements');
    }

    public function wbsItems(): HasMany
    {
        return $this->hasMany(WbsItem::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeInScope(Builder $query): Builder
    {
        return $query->where('in_scope', true);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
