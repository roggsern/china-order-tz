<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryType;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryOptionResource;
use App\Models\DeliveryOption;
use App\Models\Order;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin confirmation that negotiated Tanzania delivery will be handled by the company.
 */
class AdminDeliveryOptionController extends Controller
{
    public function confirmNegotiated(Order $order): JsonResponse
    {
        $this->authorize(AdminPermissions::ORDERS_SHIP);

        $order->loadMissing('deliveryOption');

        $option = $order->deliveryOption;
        if ($option === null) {
            throw ValidationException::withMessages([
                'order' => ['Order has no delivery option.'],
            ]);
        }

        $type = $option->delivery_type instanceof DeliveryType
            ? $option->delivery_type
            : DeliveryType::tryFrom((string) $option->delivery_type);

        if ($type !== DeliveryType::NegotiatedDelivery) {
            throw ValidationException::withMessages([
                'delivery_type' => ['Only negotiated delivery can be confirmed for company handling.'],
            ]);
        }

        $updated = DB::transaction(function () use ($option): DeliveryOption {
            /** @var DeliveryOption $locked */
            $locked = DeliveryOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();

            $status = $locked->delivery_status instanceof DeliveryOptionStatus
                ? $locked->delivery_status
                : DeliveryOptionStatus::from((string) $locked->delivery_status);

            if ($status === DeliveryOptionStatus::Completed) {
                throw ValidationException::withMessages([
                    'delivery_status' => ['Completed delivery options cannot be confirmed.'],
                ]);
            }

            if ($status === DeliveryOptionStatus::Confirmed) {
                return $locked->fresh(['order']) ?? $locked;
            }

            if ($status !== DeliveryOptionStatus::Pending) {
                throw ValidationException::withMessages([
                    'delivery_status' => ["Cannot confirm from status [{$status->value}]."],
                ]);
            }

            $locked->fill([
                'delivery_status' => DeliveryOptionStatus::Confirmed,
                'confirmed_at' => $locked->confirmed_at ?? now(),
                'notes' => filled($locked->notes)
                    ? $locked->notes
                    : 'Admin confirmed: company will handle negotiated delivery.',
            ])->save();

            return $locked->fresh(['order']) ?? $locked;
        });

        return response()->json([
            'success' => true,
            'message' => 'Negotiated delivery confirmed for company handling.',
            'data' => new DeliveryOptionResource($updated),
        ]);
    }
}
