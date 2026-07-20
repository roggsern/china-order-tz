<?php

namespace Database\Seeders;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::query()->first();
        $customer = User::query()->first();
        $product = Product::query()->first();
        $order = Order::query()->first();

        $adminEmail = $admin?->email ?? 'system';
        $productName = $product?->name ?? 'Demo Product';
        $orderNumber = $order?->order_number ?? 'COTZ-DEMO-000001';
        $productPrice = (string) ($product?->price ?? '150000');
        $orderTotal = (string) ($order?->total ?? '250000');

        $rows = [
            [
                'event_type' => ActivityEventType::AdminLogin,
                'action' => 'login',
                'actor_type' => ActivityActorType::Admin,
                'actor_id' => $admin?->id,
                'subject_type' => Admin::class,
                'subject_id' => $admin?->id,
                'description' => "Admin {$adminEmail} logged in.",
                'old_values' => null,
                'new_values' => ['email' => $adminEmail],
                'hours_ago' => 48,
            ],
            [
                'event_type' => ActivityEventType::ProductCreated,
                'action' => 'created',
                'actor_type' => ActivityActorType::Admin,
                'actor_id' => $admin?->id,
                'subject_type' => Product::class,
                'subject_id' => $product?->id,
                'description' => "Product \"{$productName}\" was created.",
                'old_values' => null,
                'new_values' => [
                    'name' => $productName,
                    'price' => $productPrice,
                ],
                'hours_ago' => 36,
            ],
            [
                'event_type' => ActivityEventType::ShippingOptionUpdated,
                'action' => 'updated',
                'actor_type' => ActivityActorType::Admin,
                'actor_id' => $admin?->id,
                'subject_type' => Product::class,
                'subject_id' => $product?->id,
                'description' => 'Shipping price for air freight was updated.',
                'old_values' => ['price' => '180000'],
                'new_values' => ['price' => '220000'],
                'hours_ago' => 30,
            ],
            [
                'event_type' => ActivityEventType::ProductUpdated,
                'action' => 'updated',
                'actor_type' => ActivityActorType::Admin,
                'actor_id' => $admin?->id,
                'subject_type' => Product::class,
                'subject_id' => $product?->id,
                'description' => "Product \"{$productName}\" was updated.",
                'old_values' => ['price' => '150000'],
                'new_values' => ['price' => '165000'],
                'hours_ago' => 28,
            ],
            [
                'event_type' => ActivityEventType::OrderCreated,
                'action' => 'created',
                'actor_type' => ActivityActorType::Customer,
                'actor_id' => $customer?->id,
                'subject_type' => Order::class,
                'subject_id' => $order?->id,
                'description' => "Order {$orderNumber} was created.",
                'old_values' => null,
                'new_values' => [
                    'order_number' => $orderNumber,
                    'total' => $orderTotal,
                ],
                'hours_ago' => 24,
            ],
            [
                'event_type' => ActivityEventType::PaymentConfirmed,
                'action' => 'confirmed',
                'actor_type' => ActivityActorType::System,
                'actor_id' => null,
                'subject_type' => Order::class,
                'subject_id' => $order?->id,
                'description' => "Payment confirmed for order {$orderNumber}.",
                'old_values' => ['status' => 'pending_payment'],
                'new_values' => ['status' => 'paid'],
                'hours_ago' => 23,
            ],
            [
                'event_type' => ActivityEventType::WarehouseJobCreated,
                'action' => 'created',
                'actor_type' => ActivityActorType::System,
                'actor_id' => null,
                'subject_type' => Order::class,
                'subject_id' => $order?->id,
                'description' => "Warehouse job was created for order {$orderNumber}.",
                'old_values' => null,
                'new_values' => ['status' => 'pending'],
                'hours_ago' => 22,
            ],
            [
                'event_type' => ActivityEventType::WarehouseStatusChanged,
                'action' => 'status_changed',
                'actor_type' => ActivityActorType::Admin,
                'actor_id' => $admin?->id,
                'subject_type' => Order::class,
                'subject_id' => $order?->id,
                'description' => 'Warehouse job status changed from picking to packed.',
                'old_values' => ['status' => 'picking'],
                'new_values' => ['status' => 'packed'],
                'hours_ago' => 18,
            ],
            [
                'event_type' => ActivityEventType::ShipmentCreated,
                'action' => 'created',
                'actor_type' => ActivityActorType::Admin,
                'actor_id' => $admin?->id,
                'subject_type' => Order::class,
                'subject_id' => $order?->id,
                'description' => "Shipment was created for order {$orderNumber}.",
                'old_values' => null,
                'new_values' => ['status' => 'pending'],
                'hours_ago' => 12,
            ],
            [
                'event_type' => ActivityEventType::TrackingEventAdded,
                'action' => 'added',
                'actor_type' => ActivityActorType::Admin,
                'actor_id' => $admin?->id,
                'subject_type' => Order::class,
                'subject_id' => $order?->id,
                'description' => 'Tracking event "departed_origin" was added.',
                'old_values' => null,
                'new_values' => ['event_type' => 'departed_origin', 'location' => 'Dar es Salaam'],
                'hours_ago' => 8,
            ],
            [
                'event_type' => ActivityEventType::NotificationSent,
                'action' => 'sent',
                'actor_type' => ActivityActorType::System,
                'actor_id' => null,
                'subject_type' => User::class,
                'subject_id' => $customer?->id,
                'description' => 'Notification tracking_updated via in_app — status sent.',
                'old_values' => null,
                'new_values' => ['channel' => 'in_app', 'status' => 'sent'],
                'hours_ago' => 7,
            ],
            [
                'event_type' => ActivityEventType::NotificationTemplateUpdated,
                'action' => 'updated',
                'actor_type' => ActivityActorType::Admin,
                'actor_id' => $admin?->id,
                'subject_type' => null,
                'subject_id' => null,
                'description' => 'Notification template "order_created.in_app" was updated.',
                'old_values' => ['is_active' => true],
                'new_values' => [
                    'is_active' => true,
                    'body' => 'Hello {{customer_name}}, order {{order_number}} created.',
                ],
                'hours_ago' => 4,
            ],
            [
                'event_type' => ActivityEventType::AdminLogout,
                'action' => 'logout',
                'actor_type' => ActivityActorType::Admin,
                'actor_id' => $admin?->id,
                'subject_type' => Admin::class,
                'subject_id' => $admin?->id,
                'description' => "Admin {$adminEmail} logged out.",
                'old_values' => null,
                'new_values' => null,
                'hours_ago' => 1,
            ],
        ];

        foreach ($rows as $row) {
            $hoursAgo = $row['hours_ago'];
            unset($row['hours_ago']);

            ActivityLog::query()->create([
                'id' => (string) Str::uuid(),
                ...$row,
                'metadata' => ['seeded' => true],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'ActivityLogSeeder',
                'created_at' => now()->subHours($hoursAgo),
            ]);
        }
    }
}
