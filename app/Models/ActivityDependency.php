<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityDependency extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'activity_id', 'predecessor_id', 'dependency_type', 'lag_days',
    ];

    protected $casts = [
        'lag_days' => 'integer',
    ];

    /** The successor activity. */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'predecessor_id');
    }
}
