<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FulfillmentOperationalReadModelResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $resource
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
