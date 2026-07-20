<?php

namespace App\Services\Crm;

use App\Enums\CustomerTimelineEventType;
use App\Events\Crm\CustomerNoteAdded;
use App\Events\Audit\CustomerNoteDeletedAudit;
use App\Events\Audit\CustomerNoteUpdatedAudit;
use App\Models\Admin;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CustomerNoteService
{
    public function __construct(
        private readonly CustomerTimelineService $timeline,
    ) {}

    public function paginate(CustomerProfile $profile, int $perPage = 20): LengthAwarePaginator
    {
        return CustomerNote::query()
            ->with('author:id,name,email')
            ->where('customer_profile_id', $profile->id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * @param  array{body: string, is_pinned?: bool}  $data
     */
    public function create(CustomerProfile $profile, array $data, ?Admin $admin = null): CustomerNote
    {
        return DB::transaction(function () use ($profile, $data, $admin) {
            $note = CustomerNote::query()->create([
                'customer_profile_id' => $profile->id,
                'created_by' => $admin?->id,
                'body' => trim($data['body']),
                'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            ]);

            $this->timeline->append(
                $profile,
                CustomerTimelineEventType::NoteAdded,
                'Internal note added',
                mb_strimwidth($note->body, 0, 120, '…'),
                CustomerNote::class,
                $note->id,
            );

            try {
                event(new CustomerNoteAdded($note, $admin));
            } catch (\Throwable $e) {
                Log::warning('crm.note_added_event_failed', ['message' => $e->getMessage()]);
            }

            return $note->load('author:id,name,email');
        });
    }

    /**
     * @param  array{body?: string, is_pinned?: bool}  $data
     */
    public function update(CustomerNote $note, array $data, ?Admin $admin = null): CustomerNote
    {
        return DB::transaction(function () use ($note, $data, $admin) {
            $before = $note->only(['body', 'is_pinned']);
            if (array_key_exists('body', $data)) {
                $body = trim((string) $data['body']);
                if ($body === '') {
                    throw ValidationException::withMessages(['body' => ['Note body cannot be empty.']]);
                }
                $note->body = $body;
            }
            if (array_key_exists('is_pinned', $data)) {
                $note->is_pinned = (bool) $data['is_pinned'];
            }
            $note->save();

            try {
                event(CustomerNoteUpdatedAudit::fromNote($note, $before, $admin));
            } catch (\Throwable $e) {
                Log::warning('crm.note_updated_audit_failed', ['message' => $e->getMessage()]);
            }

            return $note->fresh(['author:id,name,email']) ?? $note;
        });
    }

    public function delete(CustomerNote $note, ?Admin $admin = null): void
    {
        DB::transaction(function () use ($note, $admin) {
            $snapshot = $note->only(['id', 'customer_profile_id', 'body', 'is_pinned']);
            $note->delete();

            try {
                event(CustomerNoteDeletedAudit::fromSnapshot($snapshot, $admin));
            } catch (\Throwable $e) {
                Log::warning('crm.note_deleted_audit_failed', ['message' => $e->getMessage()]);
            }
        });
    }
}
