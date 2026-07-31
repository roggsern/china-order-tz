<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\ProductLifecycle;
use Illuminate\Validation\Validator;

trait ValidatesChinaImportProductSupplier
{
    protected function validateChinaImportSupplier(Validator $validator, ?Product $existingProduct = null): void
    {
        $channelId = $this->input('commerce_channel_id', $existingProduct?->commerce_channel_id);
        $channel = is_string($channelId) && $channelId !== ''
            ? CommerceChannel::query()->find($channelId)
            : null;
        $channelCode = CommerceChannelCode::tryFrom((string) ($channel?->code ?? ''))
            ?? CommerceChannelCode::fromFulfillmentSource($existingProduct?->fulfillment_source ?? null);

        if ($channelCode !== CommerceChannelCode::ChinaImport) {
            return;
        }

        if (! $this->shouldRequireChinaImportSupplier($existingProduct)) {
            return;
        }

        $supplierId = $this->has('supplier_id')
            ? $this->input('supplier_id')
            : $existingProduct?->supplier_id;

        if (blank($supplierId)) {
            $validator->errors()->add(
                'supplier_id',
                'China import products must have a supplier assigned.',
            );

            return;
        }

        $supplierActive = Supplier::query()
            ->whereKey($supplierId)
            ->where('is_active', true)
            ->exists();

        if (! $supplierActive) {
            $validator->errors()->add(
                'supplier_id',
                'Selected supplier must be active.',
            );
        }
    }

    protected function shouldRequireChinaImportSupplier(?Product $existingProduct = null): bool
    {
        if ($existingProduct !== null) {
            return $this->requestTargetsActiveLifecycleForSupplier($existingProduct);
        }

        return $this->requestTargetsActiveLifecycleForSupplier(null);
    }

    protected function requestTargetsActiveLifecycleForSupplier(?Product $existingProduct): bool
    {
        if ($this->filled('lifecycle_status') || $this->has('status')) {
            $legacyStatus = $this->has('status') && is_bool($this->input('status'))
                ? (bool) $this->input('status')
                : null;

            $lifecycle = ProductLifecycle::resolveFromRequest(
                $this->input('lifecycle_status'),
                $legacyStatus,
            );

            if ($this->has('status') && is_string($this->input('status'))) {
                $normalized = strtolower(trim($this->input('status')));
                if ($normalized === 'draft') {
                    return false;
                }
                if (in_array($normalized, ['active', 'out_of_stock', 'archived'], true)) {
                    return ProductLifecycleStatus::tryFromMixed($normalized)?->isPurchasable() ?? false;
                }
            }

            return $lifecycle->isPurchasable();
        }

        if ($existingProduct !== null) {
            $lifecycle = $existingProduct->lifecycle_status;
            if ($lifecycle instanceof ProductLifecycleStatus) {
                return $lifecycle->isPurchasable();
            }

            return ProductLifecycleStatus::tryFromMixed($lifecycle)?->isPurchasable() ?? false;
        }

        return false;
    }
}
