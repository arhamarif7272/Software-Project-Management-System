<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milestone extends Model
{
    protected $fillable = [
        'project_id', 'title', 'description',
        'due_date', 'completed_date', 'status', 'responsible_id',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'completed_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'responsible_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
