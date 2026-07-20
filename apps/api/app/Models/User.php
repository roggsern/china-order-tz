<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuidPrimaryKey, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
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
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /** Active shopping cart (Cart Engine). */
    public function activeCart(): HasOne
    {
        return $this->hasOne(Cart::class)
            ->where('status', \App\Enums\CartStatus::Active)
            ->latestOfMany();
    }

    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function shippingAddresses(): HasMany
    {
        return $this->hasMany(ShippingAddress::class);
    }

    public function deliveryAddress(): HasOne
    {
        return $this->hasOne(DeliveryAddress::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function chinaOrderRequests(): HasMany
    {
        return $this->hasMany(ChinaOrderRequest::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }
}
