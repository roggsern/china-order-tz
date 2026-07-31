<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\UserAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAddress extends Model
{
    /** @use HasFactory<UserAddressFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'region',
        'postal_code',
        'country',
        'is_shipping',
        'is_billing',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_shipping' => 'boolean',
            'is_billing' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Street / primary line (canonical column: address_line_1). */
    public function street(): string
    {
        return (string) $this->address_line_1;
    }

    /** District (canonical column: address_line_2). */
    public function district(): ?string
    {
        return $this->address_line_2;
    }
}

