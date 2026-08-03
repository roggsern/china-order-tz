<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Services\Orders\CompanyShippingReceivingChoiceService;
use App\Services\Orders\CustomerOrderListPreviewBuilder;
use App\Services\Orders\CustomerOrderPaymentStatusResolver;
use App\Services\Orders\CustomerOrderProgressResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class CustomerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof OrderStatus
            ? $this->status
            : OrderStatus::tryFrom((string) $this->status);

        $progress = app(CustomerOrderProgressResolver::class)->resolve($this->resource);
        $receivingChoice = $request->user() !== null
            ? app(CompanyShippingReceivingChoiceService::class)->snapshot($this->resource, $request->user())
            : null;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'source' => $this->resolveSource(),
            'status' => $status?->value ?? (string) $this->status,
            'status_label' => $status?->customerLabel() ?? 'Status unavailable',
            'payment_status' => app(CustomerOrderPaymentStatusResolver::class)->resolve($this->resource),
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'grand_total' => $this->grand_total,
            'total' => $this->grand_total,
            'created_at' => $this->created_at,
            'preview' => app(CustomerOrderListPreviewBuilder::class)->build($this->resource),
            'progress' => new CustomerOrderProgressResource($progress),
            'receiving_choice' => $receivingChoice,
        ];
    }
}
