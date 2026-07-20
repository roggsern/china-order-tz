<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class CustomerTag extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomerTag $tag): void {
            if (blank($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(CustomerProfile::class, 'customer_profile_tag')
            ->withPivot(['assigned_by', 'assigned_at']);
    }
}
