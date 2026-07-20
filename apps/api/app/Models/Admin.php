<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
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
}
