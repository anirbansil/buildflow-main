<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const PHASES = [
        'planning'    => 1,
        'in_progress' => 2,
        'delayed'     => 3,
        'finishing'   => 4,
        'completed'   => 5,
    ];

    public const PHASE_LABELS = [
        1 => 'Planning',
        2 => 'In Progress',
        3 => 'Delayed',
        4 => 'Finishing',
        5 => 'Completed',
    ];

    public const CATEGORIES = ['commercial', 'infrastructure', 'residential'];

    protected $fillable = [
        'name',
        'description',
        'location',
        'lat',
        'long',
        'total_budget',
        'used_budget',
        'deadline',
        'status',
        'phase',
        'category',
        'progress',
        'project_code',
        'general_contractor',
        'lead_architect',
        'site_info',
    ];

    protected static function booted(): void
    {
        static::created(function (Project $project) {
            if (! $project->project_code) {
                $project->forceFill([
                    'project_code' => 'PRJ-'.now()->year.'-'.str_pad((string) $project->id, 3, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'lat'          => 'float',
            'long'         => 'float',
            'total_budget' => 'float',
            'used_budget'  => 'float',
            'progress'     => 'integer',
            'deadline'     => 'date:Y-m-d',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_assign_users')
                    ->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProjectMaterial::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function scopeFilter($query, array $filters): void
    {
        $query->when($filters['name'] ?? null, fn ($q, $v) =>
            $q->where('name', 'like', '%' . trim($v) . '%')
        );

        $query->when($filters['location'] ?? null, fn ($q, $v) =>
            $q->where('location', 'like', '%' . trim($v) . '%')
        );

        $query->when(($filters['status'] ?? '') !== '', function ($q) use ($filters) {
            $q->where('status', $filters['status']);
        });
    }

    public function scopeOrdering($query, array $filters): void
    {
        $query->when($filters['field_name'] ?? null, fn ($q, $v) =>
            $q->orderBy($v, $filters['short_by'] ?? 'asc')
        );
    }
}
