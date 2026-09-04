<?php

namespace App\Services\Fulfillment;

use App\Models\Admin;

class FulfillmentAssigneeQuery
{
    /**
     * Eligible fulfillment owners in one query. Email is included only when
     * two or more returned admins share the same name.
     *
     * @return list<array{id: string, name: string, email?: string|null}>
     */
    public function list(): array
    {
        $admins = Admin::query()
            ->eligibleFulfillmentAssignees()
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);

        $duplicateNames = $admins
            ->countBy(static fn (Admin $admin): string => mb_strtolower(trim((string) $admin->name)))
            ->filter(static fn (int $count): bool => $count > 1);

        return $admins
            ->map(static function (Admin $admin) use ($duplicateNames): array {
                $row = [
                    'id' => (string) $admin->id,
                    'name' => (string) $admin->name,
                ];

                $nameKey = mb_strtolower(trim((string) $admin->name));
                if ($duplicateNames->has($nameKey)) {
                    $row['email'] = $admin->email;
                }

                return $row;
            })
            ->values()
            ->all();
    }
}
