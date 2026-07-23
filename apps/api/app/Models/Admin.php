<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Support\Admin\AdminPermissions;
use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasApiTokens, HasFactory, HasUuidPrimaryKey, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'phone',
        'password',
        'is_super_admin',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (Admin $admin): void {
            if ($admin->wasChanged('password')) {
                $admin->tokens()->delete();

                return;
            }

            if ($admin->wasChanged('is_active') && ! $admin->is_active) {
                $admin->tokens()->delete();
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function storeAssignments(): HasMany
    {
        return $this->hasMany(StoreUserAssignment::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user_assignments')
            ->withPivot(['assignment_type', 'starts_at', 'ends_at', 'is_active', 'assigned_by'])
            ->withTimestamps();
    }

    public function isMasterCashier(): bool
    {
        return $this->role?->slug === 'master_cashier';
    }

    public function isStoreCashier(): bool
    {
        return $this->role?->slug === 'store_cashier';
    }

    /**
     * Explicit super-admin bypass for catalogued admin permissions only.
     * Inactive admins never pass (admin.active middleware remains authoritative).
     */
    public function hasAdminPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! AdminPermissions::isKnown($permission)) {
            return false;
        }

        if ($this->is_super_admin) {
            return true;
        }

        $role = $this->relationLoaded('role') ? $this->role : $this->role()->with('permissions')->first();

        return $role?->hasPermission($permission) ?? false;
    }

    /**
     * Alias used by FormRequests / tests — does not replace Gate policies.
     */
    public function canAdmin(string $permission): bool
    {
        return $this->hasAdminPermission($permission);
    }
}
