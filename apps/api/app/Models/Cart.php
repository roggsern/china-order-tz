<?php

namespace App\Models;

use App\Enums\CartStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'session_id',
        'status',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    public function subtotal(): string
    {
        $total = '0.00';

        foreach ($this->items as $item) {
            $price = (string) ($item->price_snapshot ?? $item->unit_price);
            $total = bcadd(
                $total,
                bcmul($price, (string) $item->quantity, 2),
                2,
            );
        }

        return $total;
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function clear(): void
    {
        $this->items()->forceDelete();
    }
}
