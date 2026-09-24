<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    public const STATUSES = [
        'pending' => 1,
        'assigned' => 2,
        'in_progress' => 3,
        'completed' => 4,
        'submitted' => 5,
        'returned' => 6,
    ];

    public const STATUS_LABELS = [
        1 => 'Pending',
        2 => 'Assigned',
        3 => 'In Progress',
        4 => 'Completed',
        5 => 'Submitted',
        6 => 'Returned',
    ];

    public const PRIORITIES = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
    ];

    public const PRIORITY_LABELS = [
        1 => 'Low',
        2 => 'Medium',
        3 => 'High',
    ];

    protected $fillable = [
        'project_id',
        'task_code',
        'title',
        'description',
        'safety_note',
        'is_critical_path',
        'priority',
        'phase_label',
        'deadline',
        'deadline_time',
        'status',
        'assigned_to',
        'assigned_worker_id',
        'worker_assigned_by',
        'worker_assigned_at',
        'created_by',
        'assigned_at',
    ];

    protected static function booted(): void
    {
        static::created(function (Task $task) {
            if (! $task->task_code) {
                $task->forceFill([
                    'task_code' => 'TSK-'.str_pad((string) $task->id, 4, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_critical_path' => 'boolean',
            'deadline' => 'date:Y-m-d',
            'assigned_at' => 'datetime',
            'worker_assigned_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_worker_id');
    }

    public function workerAssignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_assigned_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }
}
