<?php

namespace App\Actions\Payments;

use App\Services\Payments\NmbCallbackService;

class HandleNmbCallbackAction
{
    public function __construct(
        private readonly NmbCallbackService $callbackService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{accepted: bool, message: string, payment_id: ?string}
     */
    public function handle(array $payload): array
    {
        return $this->callbackService->handle($payload);
    }
}
