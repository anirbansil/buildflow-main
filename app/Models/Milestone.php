<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    public const STATUSES = [
        'pending' => 1,
        'in_progress' => 2,
        'completed' => 3,
    ];

    public const STATUS_LABELS = [
        1 => 'Pending',
        2 => 'In Progress',
        3 => 'Completed',
    ];

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'due_date',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
