<?php

namespace App\Actions\AdminOrders;

use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetAdminOrdersAction
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    /**
     * @param  array{
     *   status?: string|null,
     *   q?: string|null,
     *   commerce_channel?: string|null,
     *   per_page?: int|string|null
     * }  $filters
     */
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $query = Order::query()
            ->with([
                'user',
                'commerceChannel',
                'items.product.images',
                'payments',
                'refundTransactions',
                'statusHistory',
            ])
            ->latest();

        $this->applyStatusFilter($query, $filters['status'] ?? null);
        $this->applySearchFilter($query, $filters['q'] ?? null);
        $this->applyCommerceChannelFilter($query, $filters['commerce_channel'] ?? null);

        return $query->paginate($this->resolvePerPage($filters['per_page'] ?? null));
    }

    private function resolvePerPage(int|string|null $perPage): int
    {
        $value = (int) ($perPage ?? self::DEFAULT_PER_PAGE);
        if ($value < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($value, self::MAX_PER_PAGE);
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        $normalized = strtolower(trim($status));
        if ($normalized === 'all') {
            return;
        }

        if ($normalized === OrderStatus::Pending->value) {
            $query->whereIn('status', [
                OrderStatus::Pending->value,
                OrderStatus::PendingPayment->value,
            ]);

            return;
        }

        if (OrderStatus::tryFrom($normalized) === null) {
            // Unknown filter — return empty rather than inventing status.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('status', $normalized);
    }

    /**
     * Bounded LIKE search on established identity fields only.
     */
    private function applySearchFilter(Builder $query, ?string $q): void
    {
        if ($q === null) {
            return;
        }

        $term = trim($q);
        if ($term === '') {
            return;
        }

        $like = '%'.$this->escapeLike($term).'%';

        $query->where(function (Builder $builder) use ($like, $term): void {
            $builder
                ->where('order_number', 'like', $like)
                ->orWhere('id', $term)
                ->orWhereHas('user', function (Builder $user) use ($like): void {
                    $user
                        ->where('email', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
        });
    }

    private function applyCommerceChannelFilter(Builder $query, ?string $commerceChannel): void
    {
        if ($commerceChannel === null || trim($commerceChannel) === '') {
            return;
        }

        $code = CommerceChannelCode::tryFrom(strtoupper(trim($commerceChannel)));
        if ($code === null) {
            // Defensive — FormRequest should already reject invalid values.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $builder) use ($code): void {
            $builder
                ->whereHas('commerceChannel', function (Builder $channel) use ($code): void {
                    $channel->where('code', $code->value);
                })
                ->orWhere('commerce_channel_snapshot->code', $code->value);
        });
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
