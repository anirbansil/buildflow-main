<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const STATUSES = [
        'pending_approval' => 1,
        'approved' => 2,
        'cleared' => 3,
        'rejected' => 4,
    ];

    public const STATUS_LABELS = [
        1 => 'Pending Approval',
        2 => 'Approved',
        3 => 'Cleared',
        4 => 'Rejected',
    ];

    protected $fillable = [
        'project_id',
        'task_id',
        'contractor_name',
        'contact_name',
        'invoice_number',
        'work_package',
        'description',
        'amount',
        'is_retainage',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'cleared_at',
        'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'is_retainage' => 'boolean',
            'approved_at' => 'datetime',
            'cleared_at' => 'datetime',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
