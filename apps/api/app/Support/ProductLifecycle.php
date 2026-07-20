<?php

namespace App\Support;

use App\Enums\ProductLifecycleStatus;

final class ProductLifecycle
{
    public static function resolveFromRequest(?string $lifecycleStatus, ?bool $legacyStatus): ProductLifecycleStatus
    {
        if ($lifecycleStatus !== null) {
            $resolved = ProductLifecycleStatus::tryFromMixed($lifecycleStatus);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($legacyStatus !== null) {
            return ProductLifecycleStatus::fromLegacyActive($legacyStatus);
        }

        return ProductLifecycleStatus::Draft;
    }
}
