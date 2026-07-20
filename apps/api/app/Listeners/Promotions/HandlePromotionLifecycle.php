<?php

namespace App\Listeners\Promotions;

use App\Events\Audit\PromotionActivatedAudit;
use App\Events\Audit\PromotionCreatedAudit;
use App\Events\Audit\PromotionExpiredAudit;
use App\Events\Audit\PromotionUpdatedAudit;
use App\Events\Audit\PromotionUsedAudit;
use App\Events\Promotions\PromotionActivated;
use App\Events\Promotions\PromotionCreated;
use App\Events\Promotions\PromotionExpired;
use App\Events\Promotions\PromotionUpdated;
use App\Events\Promotions\PromotionUsed;

class HandlePromotionLifecycle
{
    public function onCreated(PromotionCreated $event): void
    {
        event(PromotionCreatedAudit::fromPromotion($event->promotion, $event->admin));
    }

    public function onUpdated(PromotionUpdated $event): void
    {
        event(PromotionUpdatedAudit::fromPromotion($event->promotion, $event->before, $event->admin));
    }

    public function onActivated(PromotionActivated $event): void
    {
        event(PromotionActivatedAudit::fromPromotion($event->promotion, $event->admin));
    }

    public function onUsed(PromotionUsed $event): void
    {
        event(PromotionUsedAudit::fromUsage($event->promotion, $event->usage, $event->order));
    }

    public function onExpired(PromotionExpired $event): void
    {
        event(PromotionExpiredAudit::fromPromotion($event->promotion, $event->admin));
    }
}
