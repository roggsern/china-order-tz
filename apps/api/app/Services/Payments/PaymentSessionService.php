<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Contracts\AsyncPaymentGatewayInterface;
use App\Payments\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentSessionService
{
    /**
     * @var array<int, PaymentStatus>
     */
    private const REJECTED_STATUSES = [
        PaymentStatus::Paid,
        PaymentStatus::Expired,
        PaymentStatus::Cancelled,
    ];

    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function initiate(Payment $payment, User $user): Payment
    {
        $this->authorizePayment($payment, $user);
        $this->validatePaymentMethod($payment);
        $this->validateCanInitiate($payment);

        return DB::transaction(function () use ($payment): Payment {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateCanInitiate($lockedPayment);

            $gateway = $this->paymentService->gatewayFor($lockedPayment);

            if (! $gateway instanceof AsyncPaymentGatewayInterface) {
                $this->throwValidationError('The selected gateway does not support payment initiation.');
            }

            $result = $gateway->initiate($lockedPayment);

            if (! $result->success) {
                $this->throwValidationError($result->message);
            }

            return $lockedPayment->fresh(['order']);
        });
    }

    private function authorizePayment(Payment $payment, User $user): void
    {
        if ($payment->user_id !== $user->id) {
            abort(404);
        }
    }

    private function validatePaymentMethod(Payment $payment): void
    {
        if (! in_array($payment->method, [PaymentMethod::Nmb, PaymentMethod::BankTransfer], true)) {
            $this->throwValidationError('This payment method does not support initiation.');
        }
    }

    private function validateCanInitiate(Payment $payment): void
    {
        if (in_array($payment->status, self::REJECTED_STATUSES, true)) {
            $this->throwValidationError("Payment cannot be initiated while status is {$payment->status->value}.");
        }
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'payment' => [$message],
        ]);
    }
}
