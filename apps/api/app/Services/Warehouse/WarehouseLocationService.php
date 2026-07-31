<?php

namespace App\Services\Warehouse;

use App\Models\WarehouseBin;
use App\Models\WarehouseFacility;
use App\Models\WarehouseZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseLocationService
{
    public function listFacilities(int $perPage = 50): LengthAwarePaginator
    {
        return WarehouseFacility::query()
            ->withCount('zones')
            ->orderBy('name')
            ->paginate(max(1, min($perPage, 100)));
    }

    /**
     * @param  array{code: string, name: string, inventory_warehouse_code?: string|null, is_active?: bool}  $input
     */
    public function createFacility(array $input): WarehouseFacility
    {
        return WarehouseFacility::query()->create([
            'code' => strtoupper(trim($input['code'])),
            'name' => trim($input['name']),
            'inventory_warehouse_code' => isset($input['inventory_warehouse_code'])
                ? strtoupper((string) $input['inventory_warehouse_code'])
                : null,
            'is_active' => $input['is_active'] ?? true,
        ]);
    }

    public function listZones(?string $facilityId = null, int $perPage = 50): LengthAwarePaginator
    {
        $query = WarehouseZone::query()->with(['facility'])->withCount('bins')->orderBy('name');

        if ($facilityId) {
            $query->where('facility_id', $facilityId);
        }

        return $query->paginate(max(1, min($perPage, 100)));
    }

    /**
     * @param  array{facility_id: string, code: string, name: string, is_active?: bool}  $input
     */
    public function createZone(array $input): WarehouseZone
    {
        WarehouseFacility::query()->findOrFail($input['facility_id']);

        return WarehouseZone::query()->create([
            'facility_id' => $input['facility_id'],
            'code' => strtoupper(trim($input['code'])),
            'name' => trim($input['name']),
            'is_active' => $input['is_active'] ?? true,
        ]);
    }

    public function listBins(?string $zoneId = null, int $perPage = 50): LengthAwarePaginator
    {
        $query = WarehouseBin::query()->with(['zone.facility'])->orderBy('code');

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        return $query->paginate(max(1, min($perPage, 100)));
    }

    /**
     * @param  array{zone_id: string, code: string, name: string, is_active?: bool}  $input
     */
    public function createBin(array $input): WarehouseBin
    {
        WarehouseZone::query()->findOrFail($input['zone_id']);

        return WarehouseBin::query()->create([
            'zone_id' => $input['zone_id'],
            'code' => strtoupper(trim($input['code'])),
            'name' => trim($input['name']),
            'is_active' => $input['is_active'] ?? true,
        ]);
    }

    public function assignVariantToBin(string $variantId, string $binId, bool $primary = true): void
    {
        DB::transaction(function () use ($variantId, $binId, $primary): void {
            WarehouseBin::query()->findOrFail($binId);

            if ($primary) {
                \App\Models\ProductVariantWarehouseBin::query()
                    ->where('product_variant_id', $variantId)
                    ->update(['is_primary' => false]);
            }

            \App\Models\ProductVariantWarehouseBin::query()->updateOrCreate(
                [
                    'product_variant_id' => $variantId,
                    'warehouse_bin_id' => $binId,
                ],
                ['is_primary' => $primary],
            );
        });
    }
}
