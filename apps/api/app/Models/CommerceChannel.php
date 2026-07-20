<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CommerceChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceChannel extends Model
{
    /** @use HasFactory<CommerceChannelFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isChinaImport(): bool
    {
        return $this->code === 'CHINA_IMPORT';
    }

    public function isTzLocal(): bool
    {
        return $this->code === 'TZ_LOCAL';
    }
}
