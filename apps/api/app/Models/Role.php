<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->pluck('slug')->all();
        }

        return $this->permissions()->pluck('slug')->all();
    }

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->permissionSlugs(), true);
    }
}
