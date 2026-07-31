<?php

namespace App\Http\Resources;

use App\Models\EmailChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pending = EmailChangeRequest::query()
            ->where('user_id', $this->id)
            ->whereNull('confirmed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified_at' => $this->email_verified_at,
            'pending_email' => $pending?->new_email,
            'pending_email_expires_at' => $pending?->expires_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
