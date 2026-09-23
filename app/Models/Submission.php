<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    public const STATUSES = [
        'pending' => 1,
        'in_review' => 2,
        'pending_final_approval' => 3,
        'revision_requested' => 4,
        'rejected' => 5,
        'approved' => 6,
    ];

    public const STATUS_LABELS = [
        1 => 'Pending',
        2 => 'In Review',
        3 => 'Pending Final Approval',
        4 => 'Revision Requested',
        5 => 'Rejected',
        6 => 'Approved',
    ];

    protected $fillable = [
        'task_id',
        'project_id',
        'worker_id',
        'quantity',
        'unit',
        'worker_description',
        'supervisor_id',
        'supervisor_comment',
        'supervisor_reviewed_at',
        'reviewed_by',
        'owner_comment',
        'reviewed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'supervisor_reviewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(SubmissionMedia::class);
    }
}
