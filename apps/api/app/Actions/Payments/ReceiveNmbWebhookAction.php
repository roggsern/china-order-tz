<?php

namespace App\Actions\Payments;

class ReceiveNmbWebhookAction
{
    /**
     * @return array{accepted: bool, message: string}
     */
    public function handle(): array
    {
        return [
            'accepted' => false,
            'message' => 'NMB webhook processing is not implemented yet.',
        ];
    }
}
