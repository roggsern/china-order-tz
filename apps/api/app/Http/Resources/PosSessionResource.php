<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PosSession */
class PosSessionResource extends JsonResource
{
    /**
     * @param  array<string, mixed>|null  $summary
     */
    public function __construct($resource, private readonly ?array $summary = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'terminal_id' => $this->terminal_id,
            'admin_id' => $this->admin_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'opening_float' => $this->opening_float,
            'expected_cash' => $this->expected_cash,
            'cash_sales' => $this->cash_sales,
            'cash_refunds' => $this->cash_refunds,
            'closing_cash' => $this->closing_cash,
            'variance_amount' => $this->variance_amount,
            'variance_type' => $this->variance_type instanceof \BackedEnum
                ? $this->variance_type->value
                : $this->variance_type,
            'variance_reason' => $this->variance_reason,
            'notes' => $this->notes,
            'closing_notes' => $this->closing_notes,
            'payment_breakdown' => $this->payment_breakdown,
            'transaction_count' => $this->transaction_count,
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store->id,
                'code' => $this->store->code,
                'name' => $this->store->name,
                'theme_color' => $this->store->theme_color,
            ]),
            'terminal' => $this->whenLoaded('terminal', fn () => [
                'id' => $this->terminal->id,
                'code' => $this->terminal->code,
                'name' => $this->terminal->name,
            ]),
            'cashier' => $this->whenLoaded('admin', fn () => [
                'id' => $this->admin->id,
                'name' => $this->admin->name,
                'email' => $this->admin->email,
            ]),
            'summary' => $this->summary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
