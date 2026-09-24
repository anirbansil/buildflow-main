<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'country_code',
        'phone',
        'email',
        'password',
        'dob',
        'profile_photo',
        'status',
        'title',
        'experience_years',
        'performance_score',
        'worker_level',
        'supervisor_id',
        'company_id',
        'notifications_enabled',
        'language',
    ];

    protected $appends = ['full_name'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'notifications_enabled'  => 'boolean',
        ];
    }

    public function getRoleNameAttribute()
    {
        if ($this->roles()->exists()) {
            return $this->roles()->first()->name;
        }
        return 0;
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getDobAttribute($val)
    {
        return $val ? date('m-d-Y', strtotime($val)) : null;
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['name'] ?? null, function ($query, $search) {
            $query->whereRaw("concat(first_name,' ', last_name) like '%" . trim($search) . "%'");
        });

        $query->when($filters['email'] ?? null, function ($query, $search) {
            $query->where('email', 'like', "%" . trim($search) . "%");
        });

        $query->when($filters['phone'] ?? null, function ($query, $search) {
            $query->where('phone', 'like', "%" . trim($search) . "%");
        });

        $query->when(isset($filters['status']), function ($query) use ($filters) {
            $query->where('status', $filters['status']);
        });

        $query->when($filters['role'] ?? null, function ($query, $search) {
            $query->role($search);
        });
    }

    public function scopeOrdering($query, array $filters)
    {
        $query->when($filters['field_name'] ?? null, function ($query, $search) use ($filters) {
            $query->orderBy($filters['field_name'], $filters['short_by']);
        });
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_assign_users')
                    ->withTimestamps();
    }

    public function tasksAssigned(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The worker's fixed direct supervisor (distinct from a task's per-task assignee).
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * In-app notifications (distinct from Notifiable::notifications(), which is Laravel's own system).
     */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'user_id');
    }
}
