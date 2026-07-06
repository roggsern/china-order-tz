<?php

namespace App\Http\Resources;

use App\Payments\DTOs\InitiatePaymentResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InitiatePaymentResult */
class PaymentInitiateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'message' => $this->message,
            'checkout_request_id' => $this->checkoutRequestId,
        ];
    }
}
