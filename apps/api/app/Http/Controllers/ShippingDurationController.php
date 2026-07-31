<?php

namespace App\Http\Controllers;

use App\Services\Shipping\ShippingDurationResolver;
use Illuminate\Http\JsonResponse;

/**
 * Public read API for canonical shipping duration windows.
 */
class ShippingDurationController extends Controller
{
    public function __construct(
        private readonly ShippingDurationResolver $durations,
    ) {}

    public function index(): JsonResponse
    {
        $all = $this->durations->resolveAll();

        return response()->json([
            'success' => true,
            'data' => [
                'air' => $this->publicDuration($all['air']),
                'sea' => $this->publicDuration($all['sea']),
                'local' => $this->publicDuration($all['local']),
            ],
        ]);
    }

    /**
     * @param  array{min_days: int, max_days: int, typical_days: int, source: string, method_code: string}  $row
     * @return array{min_days: int, max_days: int, typical_days: int, method_code: string, source: string}
     */
    private function publicDuration(array $row): array
    {
        return [
            'min_days' => $row['min_days'],
            'max_days' => $row['max_days'],
            'typical_days' => $row['typical_days'],
            'method_code' => $row['method_code'],
            'source' => $row['source'],
        ];
    }
}
