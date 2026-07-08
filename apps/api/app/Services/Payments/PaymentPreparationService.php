<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentPreparationService
{
    /**
     * @var array<int, PaymentStatus>
     */
    private const ACTIVE_PAYMENT_STATUSES = [
        PaymentStatus::Pending,
        PaymentStatus::Initiated,
    ];

    public function __construct(
        private readonly PaymentReferenceGenerator $paymentReferenceGenerator,
    ) {}

    public function prepare(Order $order, User $user, PaymentMethod $method): Payment
    {
        $this->authorizeOrder($order, $user);
        $this->validateOrderNotPaid($order);

        if (bccomp((string) $order->total, '0', 2) <= 0) {
            $this->throwValidationError('order', 'Order total must be greater than zero.');
        }

        return DB::transaction(function () use ($order, $user, $method): Payment {
            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->whereIn('status', self::ACTIVE_PAYMENT_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($payment !== null) {
                return $this->reuseActivePayment($payment, $order, $method);
            }

            return Payment::query()->create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'method' => $method,
                'status' => PaymentStatus::Initiated,
                'amount' => $order->total,
                'currency' => $order->currency,
                'reference' => $this->paymentReferenceGenerator->generate(),
            ])->load('order');
        });
    }

    public function show(Order $order, User $user): Payment
    {
        $this->authorizeOrder($order, $user);

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->latest()
            ->first();

        if ($payment === null) {
            abort(404);
        }

        return $payment->load('order');
    }

    private function reuseActivePayment(Payment $payment, Order $order, PaymentMethod $method): Payment
    {
        if ($payment->method !== $method) {
            $this->throwValidationError('payment_method', 'An active payment already exists for this order.');
        }

        if (bccomp((string) $payment->amount, (string) $order->total, 2) !== 0) {
            $this->throwValidationError('order', 'Payment amount must equal the order total.');
        }

        if ($payment->reference === null) {
            $payment->reference = $this->paymentReferenceGenerator->generate();
        }

        if ($payment->status === PaymentStatus::Pending) {
            $payment->status = PaymentStatus::Initiated;
        }

        $payment->save();

        return $payment->fresh(['order']);
    }

    private function authorizeOrder(Order $order, User $user): void
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }
    }

    private function validateOrderNotPaid(Order $order): void
    {
        if ($order->status === OrderStatus::Paid) {
            $this->throwValidationError('order', 'This order has already been paid.');
        }

        if ($order->paid_at !== null) {
            $this->throwValidationError('order', 'This order has already been paid.');
        }

        $hasPaidPayment = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::Paid)
            ->exists();

        if ($hasPaidPayment) {
            $this->throwValidationError('order', 'This order has already been paid.');
        }
    }

    private function throwValidationError(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
