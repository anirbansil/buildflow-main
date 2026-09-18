<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'location',
        'lat',
        'long',
        'total_budget',
        'deadline',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'lat'          => 'float',
            'long'         => 'float',
            'total_budget' => 'float',
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
