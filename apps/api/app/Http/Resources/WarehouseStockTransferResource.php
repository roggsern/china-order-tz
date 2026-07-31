<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WarehouseStockTransfer */
class WarehouseStockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'from_facility_id' => $this->from_facility_id,
            'to_facility_id' => $this->to_facility_id,
            'status' => $status,
            'status_label' => $this->status instanceof \App\Enums\WarehouseStockTransferStatus
                ? $this->status->label()
                : null,
            'notes' => $this->notes,
            'requested_at' => $this->requested_at,
            'approved_at' => $this->approved_at,
            'transferred_at' => $this->transferred_at,
            'cancelled_at' => $this->cancelled_at,
            'from_facility' => $this->whenLoaded('fromFacility', fn () => $this->fromFacility ? [
                'id' => $this->fromFacility->id,
                'code' => $this->fromFacility->code,
                'name' => $this->fromFacility->name,
            ] : null),
            'to_facility' => $this->whenLoaded('toFacility', fn () => $this->toFacility ? [
                'id' => $this->toFacility->id,
                'code' => $this->toFacility->code,
                'name' => $this->toFacility->name,
            ] : null),
            'lines' => WarehouseStockTransferLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
