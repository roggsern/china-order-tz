<?php

namespace App\Listeners\CostProfit;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Events\Audit\CostUpdatedAudit;
use App\Events\Audit\OrderCostCapturedAudit;
use App\Events\Audit\ProfitCalculatedAudit;
use App\Events\CostProfit\CostUpdated;
use App\Events\CostProfit\LowMarginDetected;
use App\Events\CostProfit\OrderCostCaptured;
use App\Events\CostProfit\ProfitCalculated;
use App\Models\Admin;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\Log;

class HandleCostProfitLifecycle
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
    ) {}

    public function onOrderCostCaptured(OrderCostCaptured $event): void
    {
        event(OrderCostCapturedAudit::fromOrder(
            $event->order,
            count($event->snapshots),
            $event->admin,
        ));
    }

    public function onCostUpdated(CostUpdated $event): void
    {
        event(CostUpdatedAudit::fromSnapshot($event->snapshot, $event->before, $event->admin));

        $beforeTotal = (float) ($event->before['total_cost'] ?? 0);
        $afterTotal = (float) $event->snapshot->total_cost;
        if ($afterTotal > $beforeTotal && $beforeTotal > 0) {
            $this->notifyAdmins(
                NotificationEventType::CostIncreaseAlert,
                [
                    'order_item_id' => $event->snapshot->order_item_id,
                    'before_total' => number_format($beforeTotal, 2, '.', ''),
                    'after_total' => number_format($afterTotal, 2, '.', ''),
                    'currency' => $event->snapshot->currency,
                ],
                'Cost increase on order line',
            );
        }
    }

    public function onProfitCalculated(ProfitCalculated $event): void
    {
        event(ProfitCalculatedAudit::fromRecord($event->profitRecord, $event->admin));
    }

    public function onLowMargin(LowMarginDetected $event): void
    {
        $record = $event->profitRecord->loadMissing('order');
        $this->notifyAdmins(
            NotificationEventType::LowMarginAlert,
            [
                'order_number' => $record->order?->order_number,
                'order_id' => $record->order_id,
                'margin_percentage' => $record->margin_percentage,
                'threshold' => number_format($event->threshold, 2, '.', ''),
                'gross_profit' => $record->gross_profit,
                'revenue' => $record->revenue,
                'currency' => $record->currency,
            ],
            'Low margin order alert',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notifyAdmins(NotificationEventType $type, array $data, string $title): void
    {
        try {
            $admins = Admin::query()->where('is_active', true)->limit(25)->get();
            foreach ($admins as $admin) {
                $this->notifications->notifyAdmin(
                    $type,
                    $admin,
                    $data,
                    [NotificationChannel::InApp],
                    $title,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('cost_profit.notify_admins_failed', [
                'type' => $type->value,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
