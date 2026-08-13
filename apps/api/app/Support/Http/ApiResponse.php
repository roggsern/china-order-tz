<?php

namespace App\Support\Http;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * API Contract v1 foundation — additive JSON envelopes for customer/mobile clients.
 *
 * Controllers are not required to migrate yet. Prefer these helpers for new code
 * and exception renders so existing keys stay compatible with the web app.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, mixed>  $extra  Additional top-level keys (e.g. auth token fields).
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        ?array $meta = null,
        int $status = 200,
        array $extra = [],
    ): JsonResponse {
        $payload = array_merge(['success' => true], $extra);

        if ($message !== null) {
            $payload['message'] = $message;
        }

        // Keep `data` after extras so callers cannot accidentally overwrite the resource payload.
        $payload['data'] = $data;

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json(self::withRequestId($payload), $status);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, mixed>  $extra  Additional top-level keys (kept for compatibility).
     */
    public static function error(
        string $message,
        string $code,
        int $status = 400,
        mixed $data = null,
        ?array $meta = null,
        array $extra = [],
    ): JsonResponse {
        $payload = array_merge([
            'success' => false,
            'code' => $code,
            'message' => $message,
        ], $extra);

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json(self::withRequestId($payload), $status);
    }

    /**
     * Laravel-compatible validation body plus Contract v1 fields.
     *
     * @param  array<string, list<string>>  $errors
     */
    public static function validationError(
        string $message = 'The given data was invalid.',
        array $errors = [],
        int $status = 422,
    ): JsonResponse {
        return self::error(
            message: $message,
            code: 'validation_failed',
            status: $status,
            extra: ['errors' => $errors],
        );
    }

    /**
     * Domain / business-rule failure as ValidationException with Contract v1 code.
     * Preserves Laravel field `errors` and HTTP status; code is additive.
     *
     * @param  array<string, list<string>|string>  $messages
     */
    public static function throwCodedValidation(
        array $messages,
        string $code = 'business_rule_violated',
        int $status = 422,
    ): never {
        $exception = ValidationException::withMessages($messages);
        $errors = $exception->errors();
        $first = collect($errors)->flatten()->first();

        $exception->response = self::error(
            message: is_string($first) && $first !== '' ? $first : $exception->getMessage(),
            code: $code,
            status: $status,
            extra: ['errors' => $errors],
        );

        throw $exception;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function withRequestId(array $payload): array
    {
        $requestId = self::resolveRequestId();
        if ($requestId !== null && $requestId !== '' && ! array_key_exists('request_id', $payload)) {
            $payload['request_id'] = $requestId;
        }

        return $payload;
    }

    public static function resolveRequestId(): ?string
    {
        $request = request();
        if ($request === null) {
            return null;
        }

        $fromAttribute = $request->attributes->get(AssignRequestId::ATTRIBUTE);
        if (is_string($fromAttribute) && $fromAttribute !== '') {
            return $fromAttribute;
        }

        $fromHeader = $request->headers->get(AssignRequestId::HEADER);
        if (is_string($fromHeader) && $fromHeader !== '') {
            return $fromHeader;
        }

        return null;
    }
}
