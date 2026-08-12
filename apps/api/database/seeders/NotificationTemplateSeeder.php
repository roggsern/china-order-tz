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
                'channels' => [NotificationChannel::InApp, NotificationChannel::Email],
            ],
            [
                'event' => NotificationEventType::PaymentConfirmed,
                'name' => 'Payment Confirmed',
                'subject' => 'Payment confirmed for {{order_number}}',
                'body' => 'Hello {{customer_name}}, payment for order {{order_number}} has been confirmed. Total: {{order_total}} {{currency}}.',
                'channels' => [NotificationChannel::InApp, NotificationChannel::Email],
            ],
            [
                'event' => NotificationEventType::OrderCancelled,
                'name' => 'Order Cancelled',
                'subject' => 'Order {{order_number}} cancelled',
                'body' => 'Hello {{customer_name}}, order {{order_number}} has been cancelled.',
            ],
            [
                'event' => NotificationEventType::ShipmentCreated,
                'name' => 'Shipment Created',
                'subject' => 'Shipment {{shipment_number}} created',
                'body' => 'Hello {{customer_name}}, shipment {{shipment_number}} for order {{order_number}} has been created.',
            ],
            [
                'event' => NotificationEventType::ShipmentArrivedTanzania,
                'name' => 'Shipment Arrived Tanzania',
                'subject' => 'Order {{order_number}} has arrived in Tanzania',
                'body' => 'Hello {{customer_name}}, your order {{order_number}} has arrived in Tanzania at {{location}}. We will notify you about the next steps for receiving your order.',
                'channels' => [NotificationChannel::InApp, NotificationChannel::Email],
            ],
            [
                'event' => NotificationEventType::CompanyHandoverPickupRequested,
                'name' => 'Company Handover Pickup Requested',
                'subject' => 'Pickup request received for {{order_number}}',
                'body' => 'Your pickup request has been received. We will notify you about collection.',
            ],
            [
                'event' => NotificationEventType::CompanyHandoverDeliveryRequested,
                'name' => 'Company Handover Delivery Requested',
                'subject' => 'Delivery request received for {{order_number}}',
                'body' => 'Your delivery request has been received. Please contact our office to arrange delivery.',
            ],
            [
                'event' => NotificationEventType::CompanyHandoverCompletedPickup,
                'name' => 'Company Handover Completed Pickup',
                'subject' => 'Order {{order_number}} collected',
                'body' => 'Your order has been collected successfully. Thank you for shopping with us.',
            ],
            [
                'event' => NotificationEventType::CompanyHandoverCompletedDelivery,
                'name' => 'Company Handover Completed Delivery',
                'subject' => 'Order {{order_number}} delivered',
                'body' => 'Your order has been delivered successfully. Thank you for shopping with us.',
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
                'channels' => [NotificationChannel::InApp, NotificationChannel::Email],
            ],
            [
                'event' => NotificationEventType::LocalOrderCompletedPickup,
                'name' => 'Local Order Completed Pickup',
                'subject' => 'Order {{order_number}} complete',
                'body' => 'Your order is complete. Thank you for shopping with us.',
            ],
            [
                'event' => NotificationEventType::LocalOrderCompletedDeliveryArrangement,
                'name' => 'Local Order Completed Delivery Arrangement',
                'subject' => 'Order {{order_number}} complete',
                'body' => 'Your order has been completed. Thank you for shopping with us.',
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
                'event' => NotificationEventType::WarehousePickAssigned,
                'name' => 'Warehouse Pick Assigned',
                'subject' => 'Your order {{order_number}} is being picked',
                'body' => 'Hello {{customer_name}}, a picker has been assigned to your order {{order_number}}.',
            ],
            [
                'event' => NotificationEventType::WarehousePickCompleted,
                'name' => 'Warehouse Pick Completed',
                'subject' => 'Order {{order_number}} picked',
                'body' => 'Hello {{customer_name}}, all items for order {{order_number}} have been picked and are moving to packing.',
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
                'event' => NotificationEventType::WarehouseReadyForPickup,
                'name' => 'Warehouse Ready For Pickup',
                'subject' => 'Order {{order_number}} ready for collection',
                'body' => 'Hello {{customer_name}}, your order {{order_number}} is ready for collection.',
            ],
            [
                'event' => NotificationEventType::WarehouseReadyForDeliveryArrangement,
                'name' => 'Warehouse Ready For Delivery Arrangement',
                'subject' => 'Order {{order_number}} ready',
                'body' => 'Hello {{customer_name}}, your order {{order_number}} is ready. Please contact our office to arrange delivery.',
            ],
            [
                'event' => NotificationEventType::PasswordReset,
                'name' => 'Password Reset',
                'subject' => 'Reset your password',
                'body' => 'Hello {{customer_name}}, reset your password using this link: {{reset_url}} (expires in {{expires_minutes}} minutes). If you did not request this, you can ignore this message.',
                'channels' => [NotificationChannel::InApp, NotificationChannel::Email],
            ],
            [
                'event' => NotificationEventType::PasswordChanged,
                'name' => 'Password Changed',
                'subject' => 'Your password was changed',
                'body' => 'Hello {{customer_name}}, your account password was changed successfully. If you did not make this change, reset your password immediately and contact support.',
                'channels' => [NotificationChannel::InApp, NotificationChannel::Email],
            ],
            [
                'event' => NotificationEventType::EmailChangeRequested,
                'name' => 'Email Change Requested',
                'subject' => 'Confirm your new email address',
                'body' => 'Hello {{customer_name}}, confirm {{new_email}} by opening this link: {{confirm_url}} (expires in {{expires_minutes}} minutes). If you did not request this, you can ignore this message. Your current email remains {{old_email}} until confirmed.',
                'channels' => [NotificationChannel::InApp, NotificationChannel::Email],
            ],
            [
                'event' => NotificationEventType::EmailChanged,
                'name' => 'Email Changed',
                'subject' => 'Your email was updated',
                'body' => 'Hello {{customer_name}}, your account email was changed from {{old_email}} to {{new_email}}. If you did not make this change, contact support immediately.',
                'channels' => [NotificationChannel::InApp, NotificationChannel::Email],
            ],
            [
                'event' => NotificationEventType::EmailVerificationRequested,
                'name' => 'Email Verification Requested',
                'subject' => 'Verify your email address',
                'body' => 'Hello {{customer_name}}, please verify {{email}} by opening this link: {{verify_url}} (expires in {{expires_minutes}} minutes).',
                'channels' => [NotificationChannel::InApp, NotificationChannel::Email],
            ],
            [
                'event' => NotificationEventType::EmailVerified,
                'name' => 'Email Verified',
                'subject' => 'Your email is verified',
                'body' => 'Hello {{customer_name}}, your email address {{email}} has been verified successfully.',
                'channels' => [NotificationChannel::InApp, NotificationChannel::Email],
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
                'event' => NotificationEventType::RefundStarted,
                'name' => 'Refund Started',
                'subject' => 'Refund processing for {{order_number}}',
                'body' => 'Hello {{customer_name}}, a refund of {{refund_amount}} {{currency}} for order {{order_number}} is being processed.',
            ],
            [
                'event' => NotificationEventType::RefundRequested,
                'name' => 'Refund Requested',
                'subject' => 'Refund request for {{order_number}}',
                'body' => 'Hello {{customer_name}}, a refund of {{refund_amount}} {{currency}} for order {{order_number}} has been requested.',
            ],
            [
                'event' => NotificationEventType::RefundApproved,
                'name' => 'Refund Approved',
                'subject' => 'Refund approved for {{order_number}}',
                'body' => 'Hello {{customer_name}}, your refund of {{refund_amount}} {{currency}} for order {{order_number}} has been approved.',
            ],
            [
                'event' => NotificationEventType::RefundRejected,
                'name' => 'Refund Rejected',
                'subject' => 'Refund update for {{order_number}}',
                'body' => 'Hello {{customer_name}}, your refund request for order {{order_number}} was rejected.',
            ],
            [
                'event' => NotificationEventType::RefundFailed,
                'name' => 'Refund Failed',
                'subject' => 'Refund could not be completed for {{order_number}}',
                'body' => 'Hello {{customer_name}}, we could not complete your refund of {{refund_amount}} {{currency}} for order {{order_number}}. Our team will follow up.',
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
            [
                'event' => NotificationEventType::SupportTicketCreated,
                'name' => 'Support Ticket Created',
                'subject' => 'Support ticket {{ticket_number}} received',
                'body' => 'Hello {{customer_name}}, we received your support request "{{subject}}". Our team will respond soon.',
            ],
            [
                'event' => NotificationEventType::SupportTicketAssigned,
                'name' => 'Support Ticket Assigned',
                'subject' => 'Ticket {{ticket_number}} assigned',
                'body' => 'Support ticket {{ticket_number}} ({{subject}}) has been assigned for follow-up.',
            ],
            [
                'event' => NotificationEventType::SupportReplyReceived,
                'name' => 'Support Reply Received',
                'subject' => 'New reply on ticket {{ticket_number}}',
                'body' => 'Hello {{customer_name}}, you have a new reply on support ticket {{ticket_number}} regarding "{{subject}}".',
            ],
            [
                'event' => NotificationEventType::SupportTicketResolved,
                'name' => 'Support Ticket Resolved',
                'subject' => 'Ticket {{ticket_number}} resolved',
                'body' => 'Hello {{customer_name}}, your support ticket {{ticket_number}} has been marked resolved. Reply if you need further help.',
            ],
            [
                'event' => NotificationEventType::ReviewApproved,
                'name' => 'Review Approved',
                'subject' => 'Your review for {{product_name}} is now live',
                'body' => 'Hello {{customer_name}}, your review for {{product_name}} has been approved and is now visible on the product page.',
            ],
            [
                'event' => NotificationEventType::ReviewRejected,
                'name' => 'Review Rejected',
                'subject' => 'Update on your review for {{product_name}}',
                'body' => 'Hello {{customer_name}}, your review for {{product_name}} could not be published. {{moderation_note}}',
            ],
        ];

        foreach ($templates as $definition) {
            /** @var NotificationEventType $event */
            $event = $definition['event'];
            /** @var list<NotificationChannel> $channels */
            $channels = $definition['channels'] ?? [NotificationChannel::InApp];

            foreach ($channels as $channel) {
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

        // Wave 6C — short lock-screen push copy (no amounts, links, or OTP).
        $pushTemplates = [
            [
                'event' => NotificationEventType::OrderCreated,
                'name' => 'Order Created Push',
                'subject' => 'Order confirmed',
                'body' => 'Your order {{order_number}} has been placed.',
            ],
            [
                'event' => NotificationEventType::OrderCancelled,
                'name' => 'Order Cancelled Push',
                'subject' => 'Order cancelled',
                'body' => 'Your order {{order_number}} was cancelled.',
            ],
            [
                'event' => NotificationEventType::PaymentConfirmed,
                'name' => 'Payment Confirmed Push',
                'subject' => 'Payment received',
                'body' => 'Payment for order {{order_number}} was confirmed.',
            ],
            [
                'event' => NotificationEventType::ShipmentCreated,
                'name' => 'Shipment Created Push',
                'subject' => 'Shipment update',
                'body' => 'A shipment for order {{order_number}} is on the way.',
            ],
            [
                'event' => NotificationEventType::ShipmentArrivedTanzania,
                'name' => 'Shipment Arrived Tanzania Push',
                'subject' => 'Arrived in Tanzania',
                'body' => 'Order {{order_number}} has arrived in Tanzania.',
            ],
            [
                'event' => NotificationEventType::OrderDelivered,
                'name' => 'Order Delivered Push',
                'subject' => 'Order delivered',
                'body' => 'Order {{order_number}} has been delivered.',
            ],
            [
                'event' => NotificationEventType::SupportReplyReceived,
                'name' => 'Support Reply Push',
                'subject' => 'Support reply',
                'body' => 'You have a new reply on ticket {{ticket_number}}.',
            ],
            [
                'event' => NotificationEventType::PasswordChanged,
                'name' => 'Password Changed Push',
                'subject' => 'Password changed',
                'body' => 'Your account password was changed. If this was not you, contact support.',
            ],
            [
                'event' => NotificationEventType::EmailChanged,
                'name' => 'Email Changed Push',
                'subject' => 'Email updated',
                'body' => 'Your account email was updated successfully.',
            ],
        ];

        foreach ($pushTemplates as $definition) {
            /** @var NotificationEventType $event */
            $event = $definition['event'];
            $key = $event->defaultTemplateKey(NotificationChannel::Push);

            NotificationTemplate::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'channel' => NotificationChannel::Push,
                    'subject' => $definition['subject'],
                    'body' => $definition['body'],
                    'is_active' => true,
                ],
            );
        }
    }
}
