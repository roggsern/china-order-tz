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
     * @param  array<string, mixed>  $headers
     * @return array{accepted: bool, message: string, payment_id: ?string}
     */
    public function handle(array $payload, array $headers = [], string $rawBody = ''): array
    {
        return $this->callbackService->handle($payload, $headers, $rawBody);
    }
}
