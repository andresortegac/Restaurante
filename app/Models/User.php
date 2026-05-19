<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Models\BoxMovement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Request-level caches for role and permission checks.
     */
    private ?array $roleNameCache = null;

    private ?array $permissionNameCache = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_login_at',
        'previous_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'current_login_at' => 'datetime',
            'previous_login_at' => 'datetime',
        ];
    }

    /**
     * Get the roles that belong to this user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function boxMovements(): HasMany
    {
        return $this->hasMany(BoxMovement::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'reserved_by');
    }

    public function assignedDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'assigned_user_id');
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string|array $role): bool
    {
        $roleNames = $this->roleNamesForChecks();

        if (is_array($role)) {
            return ! empty(array_intersect($roleNames, $role));
        }

        return in_array($role, $roleNames, true);
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionNamesForChecks(), true);
    }

    /**
     * Check if user has all permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return empty(array_diff($permissions, $this->permissionNamesForChecks()));
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return ! empty(array_intersect($permissions, $this->permissionNamesForChecks()));
    }

    private function roleNamesForChecks(): array
    {
        if ($this->roleNameCache === null) {
            $this->loadMissing('roles.permissions');

            $this->roleNameCache = $this->roles
                ->pluck('name')
                ->all();
        }

        return $this->roleNameCache;
    }

    private function permissionNamesForChecks(): array
    {
        if ($this->permissionNameCache === null) {
            $this->loadMissing('roles.permissions');

            $this->permissionNameCache = $this->roles
                ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
                ->unique()
                ->values()
                ->all();
        }

        return $this->permissionNameCache;
    }
}
