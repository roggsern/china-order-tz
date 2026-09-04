<?php

namespace App\Services\Fulfillment;

use App\Models\Admin;
use App\Models\Fulfillment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FulfillmentBulkAssignmentService
{
    public function __construct(
        private readonly FulfillmentEngine $engine,
    ) {}

    /**
     * @param  list<string>  $fulfillmentIds
     * @return array{
     *     requested: int,
     *     changed: int,
     *     unchanged: int,
     *     assigned_to: string|null,
     *     assignee: array{id: string, name: string}|null
     * }
     */
    public function assign(Admin $actor, array $fulfillmentIds, ?string $assignedTo): array
    {
        $ids = array_values(array_map(
            static fn (string $id): string => trim($id),
            $fulfillmentIds,
        ));
        sort($ids);

        $fulfillments = Fulfillment::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(static fn (Fulfillment $fulfillment): string => (string) $fulfillment->id)
            ->values();

        if ($fulfillments->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'fulfillment_ids' => ['One or more selected fulfillments do not exist.'],
            ]);
        }

        $nextId = $assignedTo !== null && $assignedTo !== '' ? (string) $assignedTo : null;
        $assignee = $nextId !== null ? Admin::query()->find($nextId) : null;

        $changed = 0;
        $unchanged = 0;

        DB::transaction(function () use ($fulfillments, $nextId, $actor, &$changed, &$unchanged): void {
            foreach ($fulfillments as $fulfillment) {
                $previousId = $fulfillment->assigned_to !== null ? (string) $fulfillment->assigned_to : null;
                $updated = $this->engine->assign($fulfillment, $nextId, $actor);
                $resultId = $updated->assigned_to !== null ? (string) $updated->assigned_to : null;

                if ($previousId === $resultId) {
                    $unchanged++;
                } else {
                    $changed++;
                }
            }
        });

        return [
            'requested' => count($ids),
            'changed' => $changed,
            'unchanged' => $unchanged,
            'assigned_to' => $nextId,
            'assignee' => $assignee instanceof Admin
                ? [
                    'id' => (string) $assignee->id,
                    'name' => (string) $assignee->name,
                ]
                : null,
        ];
    }
}
