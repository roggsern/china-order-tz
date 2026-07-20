<?php

namespace App\Services\Fulfillment\Strategies;

use App\Enums\FulfillmentStrategy;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\Fulfillment\Contracts\FulfillmentStrategyInterface;
use App\Services\Fulfillment\FulfillmentStrategyResolver;
use Illuminate\Support\Facades\Log;

class ChinaFulfillmentStrategy implements FulfillmentStrategyInterface
{
    public function __construct(
        private readonly FulfillmentStrategyResolver $resolver,
        private readonly ChinaWorkflowEngine $chinaWorkflow,
    ) {}

    public function key(): FulfillmentStrategy
    {
        return FulfillmentStrategy::China;
    }

    public function appliesTo(Order $order): bool
    {
        return $this->resolver->orderRequiresChina($order);
    }

    public function bootstrap(Fulfillment $fulfillment): void
    {
        if (! filled($fulfillment->notes)) {
            $fulfillment->forceFill([
                'notes' => 'China procurement fulfillment. Source, QC, consolidate, then export.',
            ])->save();
        }

        try {
            $this->chinaWorkflow->bootstrapFromFulfillment($fulfillment);
        } catch (\Throwable $e) {
            // Do not roll back fulfillment creation — procurement officers can assign suppliers and retry.
            Log::warning('china.workflow_bootstrap_failed', [
                'fulfillment_id' => $fulfillment->id,
                'order_id' => $fulfillment->order_id,
                'message' => $e->getMessage(),
            ]);
            $fulfillment->forceFill([
                'notes' => trim(($fulfillment->notes ?? '').' | Procurement bootstrap deferred: '.$e->getMessage()),
            ])->save();
        }
    }
}
