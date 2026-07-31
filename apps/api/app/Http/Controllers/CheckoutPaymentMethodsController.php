<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaymentConfigurationResolver;
use Illuminate\Http\JsonResponse;

/**
 * Customer checkout payment availability from admin settings (no secrets).
 */
class CheckoutPaymentMethodsController extends Controller
{
    public function __construct(
        private readonly PaymentConfigurationResolver $resolver,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->resolver->presentCheckoutAvailability(),
        ]);
    }
}
