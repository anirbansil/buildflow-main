<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'type',
        'description',
    ];

    public static function record(int $projectId, string $type, string $description, ?int $userId = null): self
    {
        return static::create([
            'project_id' => $projectId,
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
        ]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
