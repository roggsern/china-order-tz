<?php

namespace App\Services\Procurement;

use App\Events\Procurement\SupplierCreated;
use App\Models\Admin;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Supplier CRUD + supplier↔variant product mappings.
 */
class SupplierEngine
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Supplier::query()
            ->when(($filters['is_active'] ?? null) !== null, function ($q) use ($filters) {
                $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.mb_strtolower($search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                });
            })
            ->withCount(['supplierProducts', 'purchaseOrders'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function show(Supplier $supplier): Supplier
    {
        return $supplier->load([
            'supplierProducts.variant.product',
        ])->loadCount(['supplierProducts', 'purchaseOrders']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?Admin $admin = null): Supplier
    {
        return DB::transaction(function () use ($data, $admin) {
            $name = trim((string) $data['name']);
            $code = filled($data['code'] ?? null)
                ? Str::upper(trim((string) $data['code']))
                : $this->generateCode($name);

            $supplier = Supplier::query()->create([
                'name' => $name,
                'code' => $code,
                'slug' => $this->uniqueSlug($name),
                'contact_person' => $data['contact_person'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? 'Tanzania',
                'payment_terms' => $data['payment_terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            event(new SupplierCreated($supplier, $admin));

            return $this->show($supplier);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, array $data, ?Admin $admin = null): Supplier
    {
        return DB::transaction(function () use ($supplier, $data, $admin) {
            $payload = [];
            foreach ([
                'name', 'contact_person', 'email', 'phone', 'address', 'city',
                'country', 'payment_terms', 'notes',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }

            if (array_key_exists('code', $data) && filled($data['code'])) {
                $payload['code'] = Str::upper(trim((string) $data['code']));
            }

            if (array_key_exists('is_active', $data)) {
                $payload['is_active'] = (bool) $data['is_active'];
            }

            if (array_key_exists('name', $payload) && $payload['name'] !== $supplier->name) {
                $payload['slug'] = $this->uniqueSlug((string) $payload['name'], $supplier->id);
            }

            $before = $supplier->only(array_keys($payload));
            $supplier->update($payload);

            if ($before !== [] && $supplier->wasChanged()) {
                event(new \App\Events\Procurement\SupplierUpdated(
                    $supplier->fresh() ?? $supplier,
                    $before,
                    $supplier->only(array_keys($payload)),
                    $admin,
                ));
            }

            return $this->show($supplier->fresh() ?? $supplier);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertSupplierProduct(Supplier $supplier, array $data): SupplierProduct
    {
        $variantId = (string) $data['product_variant_id'];
        ProductVariant::query()->findOrFail($variantId);

        return SupplierProduct::query()->updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'product_variant_id' => $variantId,
            ],
            [
                'supplier_sku' => $data['supplier_sku'] ?? null,
                'purchase_cost' => $data['purchase_cost'],
                'currency' => strtoupper((string) ($data['currency'] ?? 'TZS')),
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ],
        )->load('variant.product');
    }

    private function generateCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        $base = $base !== '' ? $base : 'SUP';
        $code = $base;
        $n = 1;
        while (Supplier::query()->where('code', $code)->exists()) {
            $code = $base.'_'.$n;
            $n++;
        }

        return $code;
    }

    private function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'supplier';
        $slug = $base;
        $n = 1;
        while (
            Supplier::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }
}
