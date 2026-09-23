<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionMedia extends Model
{
    protected $fillable = [
        'submission_id',
        'file_path',
        'file_name',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
