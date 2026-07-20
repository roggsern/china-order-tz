<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configurable payment method definitions (table: payment_methods).
 * Distinct from App\Enums\PaymentMethod used on payment transactions.
 */
class PaymentMethodDefinition extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'payment_methods';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'config' => 'array',
        ];
    }
}
