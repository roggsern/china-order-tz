<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\PaymentTransaction;
use App\Models\User;

class PaymentCheckoutSessionRefreshed extends BusinessAuditEvent
{
    /**
     * @param  array{provider_reference?: string|null, success_indicator?: string|null}  $before
     * @param  array{provider_reference?: string|null, success_indicator?: string|null}  $after
     */
    public static function fromTransaction(
        PaymentTransaction $transaction,
        User $actor,
        array $before,
        array $after,
    ): self {
        return self::make(
            type: ActivityEventType::PaymentCheckoutSessionRefreshed,
            actorType: ActivityActorType::Customer,
            actorId: $actor->id,
            subjectType: PaymentTransaction::class,
            subjectId: $transaction->id,
            description: sprintf(
                'NMB Hosted Checkout session refreshed for payment %s.',
                $transaction->merchant_reference,
            ),
            oldValues: $before,
            newValues: $after,
            metadata: [
                'order_id' => $transaction->order_id,
                'merchant_reference' => $transaction->merchant_reference,
                'provider' => $transaction->provider instanceof \BackedEnum
                    ? $transaction->provider->value
                    : (string) $transaction->provider,
            ],
        );
    }
}
