<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvmSnapshot extends Model
{
    // The table only has created_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'project_id', 'status_date',
        'bac', 'pv', 'ev', 'ac', 'cv', 'sv',
        'cpi', 'spi', 'eac', 'etc', 'vac',
    ];

    protected $casts = [
        'status_date' => 'date',
        'bac' => 'decimal:2',
        'pv'  => 'decimal:2',
        'ev'  => 'decimal:2',
        'ac'  => 'decimal:2',
        'cv'  => 'decimal:2',
        'sv'  => 'decimal:2',
        'cpi' => 'decimal:4',
        'spi' => 'decimal:4',
        'eac' => 'decimal:2',
        'etc' => 'decimal:2',
        'vac' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
