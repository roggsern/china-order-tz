<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerOrderProgressResource extends JsonResource
{
    /**
     * @param  array{
     *     current_key: string,
     *     current_label: string,
     *     steps: list<array{key: string, label: string, completed: bool}>
     * }  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            'current_key' => $this->resource['current_key'],
            'current_label' => $this->resource['current_label'],
            'steps' => $this->resource['steps'],
        ];
    }
}
