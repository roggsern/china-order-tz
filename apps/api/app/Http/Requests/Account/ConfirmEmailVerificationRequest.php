<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmEmailVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'hash' => ['required', 'string'],
            'expires' => ['required'],
            'signature' => ['required', 'string'],
        ];
    }

    /**
     * Build the URL Laravel's signed validator expects for the named route.
     */
    public function hasValidSignature(): bool
    {
        $id = (string) $this->input('id');
        $hash = (string) $this->input('hash');
        $expires = (string) $this->input('expires');
        $signature = (string) $this->input('signature');

        $url = url()->route('customer.verification.verify', [
            'id' => $id,
            'hash' => $hash,
        ], absolute: true);

        $candidate = $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'expires' => $expires,
            'signature' => $signature,
        ]);

        // Reconstruct request-style absolute URL and validate via UrlGenerator.
        return \Illuminate\Support\Facades\URL::hasValidSignature(
            \Illuminate\Http\Request::create($candidate, 'GET'),
        );
    }
}
