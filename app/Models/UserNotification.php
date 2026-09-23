<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    public const TYPES = ['critical_alert', 'action_required', 'milestone', 'financial', 'task'];

    protected $fillable = [
        'user_id',
        'project_id',
        'type',
        'title',
        'body',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Notify every OWNER assigned to the project.
     */
    public static function notifyOwners(Project $project, string $type, string $title, ?string $body = null, ?string $actionUrl = null): void
    {
        $ownerIds = $project->users()->role('OWNER')->pluck('users.id');

        foreach ($ownerIds as $ownerId) {
            static::create([
                'user_id' => $ownerId,
                'project_id' => $project->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'action_url' => $actionUrl,
            ]);
        }
    }
}
