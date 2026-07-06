<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookAcknowledgmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'accepted' => $this->resource['accepted'],
            'message' => $this->resource['message'],
        ];
    }
}
