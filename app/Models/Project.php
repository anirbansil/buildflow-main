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

    public function quarterLabel(): ?string
    {
        if (! $this->start_date || ! $this->deadline) {
            return null;
        }

        $quarterOf = fn ($date) => 'Q'.(int) ceil($date->month / 3).' '.$date->year;

        return $quarterOf($this->start_date).' - '.$quarterOf($this->deadline);
    }

    /**
     * How much total_budget has grown from initial_budget, as a percent. Positive = over the
     * original estimate. Null when there's nothing to compare against.
     */
    public function budgetVariancePercent(): ?float
    {
        if (! $this->initial_budget || $this->initial_budget <= 0 || $this->total_budget === null) {
            return null;
        }

        return round((($this->total_budget - $this->initial_budget) / $this->initial_budget) * 100, 1);
    }

    protected $fillable = [
        'name',
        'description',
        'location',
        'lat',
        'long',
        'total_budget',
        'initial_budget',
        'used_budget',
        'retainage_rate',
        'start_date',
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
        static::creating(function (Project $project) {
            if ($project->initial_budget === null) {
                $project->initial_budget = $project->total_budget;
            }
        });

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
            'initial_budget' => 'float',
            'used_budget'  => 'float',
            'retainage_rate' => 'float',
            'progress'     => 'integer',
            'start_date'   => 'date:Y-m-d',
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

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
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
