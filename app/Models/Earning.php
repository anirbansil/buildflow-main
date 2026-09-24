<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Earning extends Model
{
    public const STATUSES = [
        'pending' => 1,
        'approved' => 2,
        'processing' => 3,
        'paid' => 4,
    ];

    public const STATUS_LABELS = [
        1 => 'Pending',
        2 => 'Approved',
        3 => 'Processing',
        4 => 'Paid',
    ];

    protected $fillable = [
        'project_id',
        'task_id',
        'worker_id',
        'title',
        'subtitle',
        'amount',
        'status',
        'earned_at',
        'created_by',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'earned_at' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }
}
