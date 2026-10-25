<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stakeholder extends Model
{
    protected $fillable = [
        'project_id', 'name', 'role', 'organization',
        'influence', 'interest', 'contact',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
