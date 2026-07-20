<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PosPaymentHandler;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\PaymentMethodDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPosPaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PaymentMethodDefinition::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        abort_unless($admin->is_super_admin, 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:payment_methods,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'config' => ['nullable', 'array'],
            'config.handler' => ['nullable', Rule::in(array_column(PosPaymentHandler::cases(), 'value'))],
            'config.pos_enabled' => ['nullable', 'boolean'],
        ]);

        $method = PaymentMethodDefinition::query()->create([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'config' => array_merge([
                'handler' => PosPaymentHandler::ManualConfirm->value,
                'pos_enabled' => true,
            ], $data['config'] ?? []),
        ]);

        return response()->json(['success' => true, 'data' => $method], 201);
    }

    public function update(Request $request, PaymentMethodDefinition $paymentMethod): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        abort_unless($admin->is_super_admin, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'config' => ['nullable', 'array'],
        ]);

        if (isset($data['config'])) {
            $data['config'] = array_merge($paymentMethod->config ?? [], $data['config']);
        }

        $paymentMethod->fill($data)->save();

        return response()->json(['success' => true, 'data' => $paymentMethod->fresh()]);
    }
}
