<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMaterial extends Model
{
    public const STATUSES = [
        'on_track'  => 1,
        'delayed'   => 2,
        'delivered' => 3,
    ];

    public const STATUS_LABELS = [
        1 => 'On Track',
        2 => 'Delayed',
        3 => 'Delivered',
    ];

    protected $fillable = [
        'project_id',
        'name',
        'unit',
        'estimated_qty',
        'delivered_qty',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'estimated_qty' => 'float',
            'delivered_qty' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
