<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Enums\PosReceiptLayout;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PosReceipt;
use App\Models\PosSession;
use App\Models\RefundTransaction;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\StoreUserAssignment;

class StorePlatformAudit extends BusinessAuditEvent
{
    public static function storeCreated(Store $store, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::StoreCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Store::class,
            subjectId: $store->id,
            description: 'Store created: '.$store->name,
            newValues: ['code' => $store->code, 'name' => $store->name],
        );
    }

    public static function storeStatus(Store $store, bool $activated, ?Admin $admin = null): self
    {
        return self::make(
            type: $activated ? ActivityEventType::StoreActivated : ActivityEventType::StoreDeactivated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: Store::class,
            subjectId: $store->id,
            description: sprintf('Store %s: %s', $store->name, $activated ? 'activated' : 'deactivated'),
        );
    }

    public static function cashierAssigned(StoreUserAssignment $assignment, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CashierAssignedToStore,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: StoreUserAssignment::class,
            subjectId: $assignment->id,
            description: 'Cashier assigned to store',
            newValues: [
                'admin_id' => $assignment->admin_id,
                'store_id' => $assignment->store_id,
                'assignment_type' => $assignment->assignment_type?->value,
            ],
        );
    }

    public static function cashierRemoved(StoreUserAssignment $assignment, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CashierRemovedFromStore,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: StoreUserAssignment::class,
            subjectId: $assignment->id,
            description: 'Cashier removed from store',
            oldValues: [
                'admin_id' => $assignment->admin_id,
                'store_id' => $assignment->store_id,
            ],
        );
    }

    public static function sessionOpened(PosSession $session, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PosSessionOpened,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id ?? $session->admin_id,
            subjectType: PosSession::class,
            subjectId: $session->id,
            description: 'POS session opened',
            newValues: [
                'store_id' => $session->store_id,
                'terminal_id' => $session->terminal_id,
                'opening_float' => $session->opening_float,
            ],
        );
    }

    public static function sessionClosed(PosSession $session, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PosSessionClosed,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id ?? $session->admin_id,
            subjectType: PosSession::class,
            subjectId: $session->id,
            description: 'POS session closed',
            newValues: [
                'expected_cash' => $session->expected_cash,
                'closing_cash' => $session->closing_cash,
                'variance_amount' => $session->variance_amount,
                'variance_type' => $session->variance_type instanceof \BackedEnum
                    ? $session->variance_type->value
                    : $session->variance_type,
                'transaction_count' => $session->transaction_count,
            ],
        );
    }

    public static function varianceDetected(PosSession $session, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PosVarianceDetected,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id ?? $session->admin_id,
            subjectType: PosSession::class,
            subjectId: $session->id,
            description: 'POS cash variance detected',
            newValues: [
                'expected_cash' => $session->expected_cash,
                'closing_cash' => $session->closing_cash,
                'variance_amount' => $session->variance_amount,
                'variance_type' => $session->variance_type instanceof \BackedEnum
                    ? $session->variance_type->value
                    : $session->variance_type,
                'variance_reason' => $session->variance_reason,
            ],
        );
    }

    public static function floatUpdated(
        PosSession $session,
        string $previousFloat,
        string $nextFloat,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::PosFloatUpdated,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id ?? $session->admin_id,
            subjectType: PosSession::class,
            subjectId: $session->id,
            description: 'POS opening float updated',
            oldValues: ['opening_float' => $previousFloat],
            newValues: ['opening_float' => $nextFloat],
        );
    }

    public static function saleCompleted(Order $order, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PosSaleCompleted,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id,
            subjectType: Order::class,
            subjectId: $order->id,
            description: 'POS sale completed: '.$order->order_number,
            newValues: [
                'store_id' => $order->store_id,
                'total' => $order->total,
                'sales_origin' => $order->sales_origin?->value,
            ],
        );
    }

    public static function receiptGenerated(PosReceipt $receipt, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PosReceiptGenerated,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id,
            subjectType: PosReceipt::class,
            subjectId: $receipt->id,
            description: 'POS receipt generated: '.$receipt->receipt_number,
            newValues: [
                'receipt_number' => $receipt->receipt_number,
                'order_id' => $receipt->order_id,
                'store_id' => $receipt->store_id,
            ],
        );
    }

    public static function receiptPrinted(
        PosReceipt $receipt,
        ?Admin $admin = null,
        ?PosReceiptLayout $layout = null,
    ): self {
        return self::make(
            type: ActivityEventType::PosReceiptPrinted,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id,
            subjectType: PosReceipt::class,
            subjectId: $receipt->id,
            description: 'POS receipt printed: '.$receipt->receipt_number,
            newValues: [
                'receipt_number' => $receipt->receipt_number,
                'layout' => $layout?->value,
                'print_count' => $receipt->print_count,
            ],
        );
    }

    public static function receiptReprinted(
        PosReceipt $receipt,
        ?Admin $admin = null,
        ?PosReceiptLayout $layout = null,
    ): self {
        return self::make(
            type: ActivityEventType::PosReceiptReprinted,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id,
            subjectType: PosReceipt::class,
            subjectId: $receipt->id,
            description: 'POS receipt reprinted: '.$receipt->receipt_number,
            newValues: [
                'receipt_number' => $receipt->receipt_number,
                'layout' => $layout?->value,
                'print_count' => $receipt->print_count,
            ],
        );
    }

    public static function returnStarted(Order $order, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PosReturnStarted,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id,
            subjectType: Order::class,
            subjectId: $order->id,
            description: 'POS return started for order '.$order->order_number,
        );
    }

    public static function returnCompleted(ReturnRequest $return, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PosReturnCompleted,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id,
            subjectType: ReturnRequest::class,
            subjectId: $return->id,
            description: 'POS return completed: '.($return->return_number ?? $return->id),
            newValues: [
                'return_number' => $return->return_number,
                'refund_total' => $return->refund_total,
                'return_type' => $return->return_type instanceof \BackedEnum
                    ? $return->return_type->value
                    : $return->return_type,
            ],
        );
    }

    public static function refundIssued(RefundTransaction $refund, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PosRefundIssued,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id,
            subjectType: RefundTransaction::class,
            subjectId: $refund->id,
            description: 'POS refund issued',
            newValues: [
                'amount' => $refund->amount,
                'method' => $refund->method,
                'order_id' => $refund->order_id,
            ],
        );
    }

    public static function exchangeCompleted(ReturnRequest $return, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::PosExchangeCompleted,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id,
            subjectType: ReturnRequest::class,
            subjectId: $return->id,
            description: 'POS exchange completed: '.($return->return_number ?? $return->id),
        );
    }

    public static function inventoryReturned(
        Order $order,
        ?string $variantId,
        int $quantity,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::PosInventoryReturned,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id,
            subjectType: Order::class,
            subjectId: $order->id,
            description: 'POS inventory returned to sellable stock',
            newValues: [
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
            ],
        );
    }

    public static function inventoryMarkedDamaged(
        Order $order,
        ?string $variantId,
        int $quantity,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::PosInventoryMarkedDamaged,
            actorType: ActivityActorType::Admin,
            actorId: $admin?->id,
            subjectType: Order::class,
            subjectId: $order->id,
            description: 'POS inventory marked damaged (not restocked)',
            newValues: [
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
            ],
        );
    }
}
