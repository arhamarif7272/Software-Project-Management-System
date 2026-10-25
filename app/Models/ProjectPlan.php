<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPlan extends Model
{
    protected $fillable = [
    'project_id', 'scope_statement', 'schedule_approach',
    'cost_approach', 'quality_approach', 
    'responsibilities', // <-- ADD THIS
    'cpi_threshold', 'spi_threshold', 'budget_alert_pct', 'version',
];

    protected $casts = [
        'cpi_threshold'    => 'decimal:2',
        'spi_threshold'    => 'decimal:2',
        'budget_alert_pct' => 'decimal:2',
        'version'          => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
