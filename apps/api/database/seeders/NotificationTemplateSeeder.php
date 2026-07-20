<?php

namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'event' => NotificationEventType::OrderCreated,
                'name' => 'Order Created',
                'subject' => 'Order {{order_number}} received',
                'body' => 'Hello {{customer_name}}, your order {{order_number}} has been created. Total: {{order_total}} {{currency}}.',
            ],
            [
                'event' => NotificationEventType::PaymentConfirmed,
                'name' => 'Payment Confirmed',
                'subject' => 'Payment confirmed for {{order_number}}',
                'body' => 'Hello {{customer_name}}, payment for order {{order_number}} has been confirmed.',
            ],
            [
                'event' => NotificationEventType::ShipmentCreated,
                'name' => 'Shipment Created',
                'subject' => 'Shipment {{shipment_number}} created',
                'body' => 'Hello {{customer_name}}, shipment {{shipment_number}} for order {{order_number}} has been created.',
            ],
            [
                'event' => NotificationEventType::TrackingUpdated,
                'name' => 'Tracking Updated',
                'subject' => 'Tracking update for {{order_number}}',
                'body' => 'Hello {{customer_name}}, order {{order_number}} tracking updated: {{tracking_status}}.',
            ],
            [
                'event' => NotificationEventType::OrderDelivered,
                'name' => 'Delivered',
                'subject' => 'Order {{order_number}} delivered',
                'body' => 'Hello {{customer_name}}, your order {{order_number}} has been delivered.',
            ],
            [
                'event' => NotificationEventType::OtpRequested,
                'name' => 'OTP',
                'subject' => 'Your verification code',
                'body' => 'Hello {{customer_name}}, your OTP is {{otp_code}}. It expires in {{otp_expires_minutes}} minutes.',
            ],
            [
                'event' => NotificationEventType::WarehousePickingStarted,
                'name' => 'Warehouse Picking Started',
                'subject' => 'Order {{order_number}} is being picked',
                'body' => 'Hello {{customer_name}}, warehouse picking has started for order {{order_number}}.',
            ],
            [
                'event' => NotificationEventType::WarehousePacked,
                'name' => 'Warehouse Packed',
                'subject' => 'Order {{order_number}} packed',
                'body' => 'Hello {{customer_name}}, order {{order_number}} has been packed.',
            ],
            [
                'event' => NotificationEventType::WarehouseReadyToShip,
                'name' => 'Warehouse Ready To Ship',
                'subject' => 'Order {{order_number}} ready to ship',
                'body' => 'Hello {{customer_name}}, order {{order_number}} is ready to ship.',
            ],
            [
                'event' => NotificationEventType::PasswordReset,
                'name' => 'Password Reset',
                'subject' => 'Reset your password',
                'body' => 'Hello {{customer_name}}, use code {{reset_code}} to reset your password.',
            ],
            [
                'event' => NotificationEventType::ReturnRequested,
                'name' => 'Return Requested',
                'subject' => 'Return request for {{order_number}}',
                'body' => 'Hello {{customer_name}}, we received your return request for order {{order_number}}.',
            ],
            [
                'event' => NotificationEventType::ReturnApproved,
                'name' => 'Return Approved',
                'subject' => 'Return approved for {{order_number}}',
                'body' => 'Hello {{customer_name}}, your return request for order {{order_number}} has been approved.',
            ],
            [
                'event' => NotificationEventType::ReturnRejected,
                'name' => 'Return Rejected',
                'subject' => 'Return update for {{order_number}}',
                'body' => 'Hello {{customer_name}}, your return request for order {{order_number}} was rejected.',
            ],
            [
                'event' => NotificationEventType::RefundCompleted,
                'name' => 'Refund Completed',
                'subject' => 'Refund completed for {{order_number}}',
                'body' => 'Hello {{customer_name}}, a refund of {{refund_amount}} {{currency}} for order {{order_number}} has been completed.',
            ],
            [
                'event' => NotificationEventType::PurchaseOrderConfirmed,
                'name' => 'Purchase Order Confirmed',
                'subject' => 'PO {{purchase_number}} confirmed',
                'body' => 'Purchase order {{purchase_number}} for supplier {{supplier_name}} has been confirmed.',
            ],
            [
                'event' => NotificationEventType::GoodsReceived,
                'name' => 'Goods Received',
                'subject' => 'Goods received for PO {{purchase_number}}',
                'body' => 'Goods were received for purchase order {{purchase_number}} (supplier {{supplier_name}}). Inventory was updated.',
            ],
            [
                'event' => NotificationEventType::LowMarginAlert,
                'name' => 'Low Margin Alert',
                'subject' => 'Low margin on order {{order_number}}',
                'body' => 'Order {{order_number}} margin is {{margin_percentage}}% (threshold {{threshold}}%). Gross profit: {{gross_profit}} {{currency}}.',
            ],
            [
                'event' => NotificationEventType::CostIncreaseAlert,
                'name' => 'Cost Increase Alert',
                'subject' => 'Cost increase on order line',
                'body' => 'Order line cost increased from {{before_total}} to {{after_total}} {{currency}} (item {{order_item_id}}).',
            ],
        ];

        foreach ($templates as $definition) {
            /** @var NotificationEventType $event */
            $event = $definition['event'];
            $channel = NotificationChannel::InApp;
            $key = $event->defaultTemplateKey($channel);

            NotificationTemplate::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'channel' => $channel,
                    'subject' => $definition['subject'],
                    'body' => $definition['body'],
                    'is_active' => true,
                ],
            );
        }
    }
}
