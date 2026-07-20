<?php

namespace Database\Seeders;

use App\Enums\PosPaymentHandler;
use App\Models\PaymentMethodDefinition;
use Illuminate\Database\Seeder;

class PosPaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'code' => 'CASH',
                'name' => 'Cash',
                'description' => 'Cash tender with change calculation',
                'sort_order' => 1,
                'config' => [
                    'handler' => PosPaymentHandler::CashWithChange->value,
                    'pos_enabled' => true,
                ],
            ],
            [
                'code' => 'MPESA_LIPA',
                'name' => 'M-Pesa Lipa Number',
                'description' => 'Customer pays company Lipa number; cashier confirms',
                'sort_order' => 2,
                'config' => [
                    'handler' => PosPaymentHandler::ManualConfirm->value,
                    'pos_enabled' => true,
                ],
            ],
            [
                'code' => 'NMB_BANK',
                'name' => 'NMB Bank',
                'description' => 'Customer pays company NMB account; cashier confirms',
                'sort_order' => 3,
                'config' => [
                    'handler' => PosPaymentHandler::ManualConfirm->value,
                    'pos_enabled' => true,
                ],
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethodDefinition::query()->updateOrCreate(
                ['code' => $method['code']],
                [
                    'name' => $method['name'],
                    'description' => $method['description'],
                    'is_active' => true,
                    'sort_order' => $method['sort_order'],
                    'config' => $method['config'],
                ],
            );
        }
    }
}
