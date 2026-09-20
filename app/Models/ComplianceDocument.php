<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceDocument extends Model
{
    public const TYPE_LABELS = [
        'insurance' => 'Certificate of Liability Insurance',
        'w9' => 'W-9 Tax Form',
        'license' => 'Contractor License Verification',
        'other' => 'Other Document',
    ];

    public const REQUIRED_TYPES = ['insurance', 'w9', 'license'];

    protected $fillable = [
        'company_id',
        'uploaded_by',
        'type',
        'title',
        'file_path',
        'file_name',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date:Y-m-d',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }
}
