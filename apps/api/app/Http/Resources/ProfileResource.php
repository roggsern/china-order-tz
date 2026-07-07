<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name ?? $this->resolveFirstName(),
            'last_name' => $this->last_name ?? $this->resolveLastName(),
            'phone' => $this->phone,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveFirstName(): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name), 2);

        return $parts[0] ?? null;
    }

    private function resolveLastName(): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name), 2);

        return $parts[1] ?? null;
    }
}
