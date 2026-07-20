<?php

namespace App\Support\Pos;

use Illuminate\Validation\ValidationException;

/**
 * Consistent POS domain error messages (422 ValidationException).
 */
final class PosErrors
{
    public static function sessionClosed(): never
    {
        throw ValidationException::withMessages([
            'session' => ['Session Closed. Open a POS session before continuing.'],
        ]);
    }

    public static function sessionRequired(): never
    {
        throw ValidationException::withMessages([
            'session' => ['Session Closed. Open a POS session first.'],
        ]);
    }

    public static function insufficientInventory(string $field = 'items'): never
    {
        throw ValidationException::withMessages([
            $field => ['Insufficient Inventory for the requested quantity.'],
        ]);
    }

    public static function duplicateSale(): never
    {
        throw ValidationException::withMessages([
            'idempotency_key' => ['Duplicate Sale. This request was already processed.'],
        ]);
    }

    public static function returnQuantityExceeded(): never
    {
        throw ValidationException::withMessages([
            'items' => ['Return Quantity Exceeded. Quantity exceeds remaining returnable amount.'],
        ]);
    }

    public static function receiptNotFound(): never
    {
        throw ValidationException::withMessages([
            'receipt' => ['Receipt Not Found.'],
        ]);
    }

    public static function storeAccessDenied(): never
    {
        throw ValidationException::withMessages([
            'store_id' => ['Store Access Denied. You are not assigned to this store.'],
        ]);
    }

    public static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
