<?php

namespace App\Actions\Payments;

use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the authoritative payment transaction for the NMB browser return page.
 */
class ResolvePaymentReturnTransactionAction
{
    public function handle(User $user, ?string $orderId, ?string $merchantReference): PaymentTransaction
    {
        $orderId = filled($orderId) ? trim((string) $orderId) : null;
        $merchantReference = filled($merchantReference) ? trim((string) $merchantReference) : null;

        if ($orderId === null && $merchantReference === null) {
            throw ValidationException::withMessages([
                'order_id' => ['An order id or merchant reference is required to resolve the payment return.'],
            ]);
        }

        if ($merchantReference === null && $this->looksLikeMerchantReference($orderId)) {
            $merchantReference = $orderId;
            $orderId = null;
        }

        $query = PaymentTransaction::query()
            ->whereHas('order', fn (Builder $builder) => $builder->where('user_id', $user->id));

        if ($merchantReference !== null) {
            $query->where('merchant_reference', $merchantReference);
        } else {
            $query->where(function (Builder $builder) use ($orderId): void {
                $builder
                    ->where('order_id', $orderId)
                    ->orWhereHas('order', fn (Builder $orderQuery) => $orderQuery->where('order_number', $orderId));
            });
        }

        $transactions = $query
            ->orderByDesc('created_at')
            ->get();

        if ($transactions->isEmpty()) {
            abort(404);
        }

        $successful = $transactions
            ->first(fn (PaymentTransaction $transaction): bool => $this->status($transaction) === PaymentTransactionStatus::Successful);

        if ($successful !== null) {
            return $successful->load('order');
        }

        $active = $transactions
            ->first(fn (PaymentTransaction $transaction): bool => in_array(
                $this->status($transaction),
                [PaymentTransactionStatus::Pending, PaymentTransactionStatus::Processing],
                true,
            ));

        return ($active ?? $transactions->first())->load('order');
    }

    private function looksLikeMerchantReference(?string $value): bool
    {
        return $value !== null && preg_match('/^COTZ-PAY-\d{8}-\d{6}$/', $value) === 1;
    }

    private function status(PaymentTransaction $transaction): PaymentTransactionStatus
    {
        return $transaction->status instanceof PaymentTransactionStatus
            ? $transaction->status
            : PaymentTransactionStatus::from((string) $transaction->status);
    }
}
