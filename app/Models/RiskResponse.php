<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskResponse extends Model
{
    protected $fillable = [
        'risk_id', 'parent_id', 'node_type', 'description', 'owner_id', 'status',
    ];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RiskResponse::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(RiskResponse::class, 'parent_id');
    }

    /** Full PDPC branch: problem -> preventive/corrective action -> contingency. */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'owner_id');
    }
}
