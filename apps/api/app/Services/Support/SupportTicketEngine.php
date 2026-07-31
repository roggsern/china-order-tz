<?php

namespace App\Services\Support;

use App\Enums\NotificationEventType;
use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Events\Audit\SupportMessageSentAudit;
use App\Events\Audit\SupportTicketAssignedAudit;
use App\Events\Audit\SupportTicketCreatedAudit;
use App\Events\Audit\SupportTicketStatusChangedAudit;
use App\Models\Admin;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Stores\ActiveStoreContext;
use App\Support\Admin\AdminPermissions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportTicketEngine
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
        private readonly ActiveStoreContext $storeContext,
    ) {}

    /**
     * @param  array{subject: string, category: string, priority?: string|null, order_id?: string|null, message: string}  $data
     */
    public function createForCustomer(User $customer, array $data): SupportTicket
    {
        return DB::transaction(function () use ($customer, $data) {
            $order = null;
            if (! empty($data['order_id'])) {
                $order = Order::query()
                    ->where('id', $data['order_id'])
                    ->where('user_id', $customer->id)
                    ->first();

                if ($order === null) {
                    throw ValidationException::withMessages([
                        'order_id' => ['Order not found or does not belong to you.'],
                    ]);
                }
            }

            $ticket = SupportTicket::query()->create([
                'ticket_number' => $this->generateTicketNumber(),
                'customer_id' => $customer->id,
                'order_id' => $order?->id,
                'subject' => $data['subject'],
                'category' => SupportTicketCategory::from($data['category']),
                'priority' => SupportTicketPriority::tryFrom($data['priority'] ?? 'normal')
                    ?? SupportTicketPriority::Normal,
                'status' => SupportTicketStatus::New,
            ]);

            $this->addMessage(
                $ticket,
                SupportMessageSenderType::Customer,
                $customer->id,
                $data['message'],
            );

            event(SupportTicketCreatedAudit::fromTicket($ticket));

            $this->notifications->notifyCustomer(
                NotificationEventType::SupportTicketCreated,
                $customer,
                $this->notificationPayload($ticket),
                idempotencyKey: 'support_ticket_created:'.$ticket->id,
            );

            return $ticket->fresh(['customer', 'order', 'assignedAdmin', 'messages']);
        });
    }

    /**
     * @param  array{status?: string|null, category?: string|null, priority?: string|null, per_page?: int|null}  $filters
     */
    public function paginateForCustomer(User $customer, array $filters = []): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->with(['order:id,order_number,store_id', 'assignedAdmin:id,name'])
            ->where('customer_id', $customer->id)
            ->orderByDesc('updated_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(max(1, min((int) ($filters['per_page'] ?? 20), 50)));
    }

    public function showForCustomer(User $customer, SupportTicket $ticket): SupportTicket
    {
        $this->assertCustomerOwns($customer, $ticket);

        return $ticket->load(['customer', 'order', 'assignedAdmin', 'messages']);
    }

    public function addCustomerMessage(User $customer, SupportTicket $ticket, string $message): SupportTicket
    {
        $this->assertCustomerOwns($customer, $ticket);

        if (in_array($ticket->status, [SupportTicketStatus::Closed, SupportTicketStatus::Resolved], true)) {
            throw ValidationException::withMessages([
                'message' => ['This ticket is closed. Please open a new ticket or wait for it to be reopened.'],
            ]);
        }

        return DB::transaction(function () use ($customer, $ticket, $message) {
            $this->addMessage($ticket, SupportMessageSenderType::Customer, $customer->id, $message);

            if ($ticket->status === SupportTicketStatus::WaitingCustomer) {
                $this->transitionStatus($ticket, SupportTicketStatus::InProgress);
            }

            $ticket->touch();

            return $ticket->fresh(['customer', 'order', 'assignedAdmin', 'messages']);
        });
    }

    /**
     * @param  array{status?: string|null, category?: string|null, priority?: string|null, assigned_admin_id?: string|null, per_page?: int|null}  $filters
     */
    public function paginateForAdmin(Admin $admin, array $filters = []): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->with(['customer:id,name,email', 'order:id,order_number,store_id', 'assignedAdmin:id,name'])
            ->orderByDesc('updated_at');

        $this->applyAdminStoreScope($admin, $query);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['assigned_admin_id'])) {
            $query->where('assigned_admin_id', $filters['assigned_admin_id']);
        }

        return $query->paginate(max(1, min((int) ($filters['per_page'] ?? 20), 100)));
    }

    public function showForAdmin(Admin $admin, SupportTicket $ticket): SupportTicket
    {
        $this->assertAdminCanAccess($admin, $ticket);

        return $ticket->load(['customer', 'order', 'assignedAdmin', 'messages']);
    }

    public function assign(Admin $actor, SupportTicket $ticket, Admin $assignee): SupportTicket
    {
        $this->assertAdminCanAccess($actor, $ticket);

        $previous = $ticket->assigned_admin_id;

        $ticket->forceFill([
            'assigned_admin_id' => $assignee->id,
        ]);

        if ($ticket->status === SupportTicketStatus::New) {
            $ticket->status = SupportTicketStatus::Open;
        }

        $ticket->save();

        event(SupportTicketAssignedAudit::fromTicket($ticket, $previous, $actor));

        $this->notifications->notifyAdmin(
            NotificationEventType::SupportTicketAssigned,
            $assignee,
            $this->notificationPayload($ticket->fresh(['customer', 'order'])),
            idempotencyKey: 'support_ticket_assigned:'.$ticket->id.':'.$assignee->id,
        );

        if ($ticket->customer) {
            $this->notifications->notifyCustomer(
                NotificationEventType::SupportTicketAssigned,
                $ticket->customer,
                $this->notificationPayload($ticket),
                idempotencyKey: 'support_ticket_assigned_customer:'.$ticket->id,
            );
        }

        return $ticket->fresh(['customer', 'order', 'assignedAdmin', 'messages']);
    }

    public function updateStatus(Admin $actor, SupportTicket $ticket, SupportTicketStatus $status): SupportTicket
    {
        $this->assertAdminCanAccess($actor, $ticket);

        $previous = $ticket->status?->value ?? (string) $ticket->status;

        if (! $ticket->status->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => [sprintf('Cannot transition from %s to %s.', $previous, $status->value)],
            ]);
        }

        $this->transitionStatus($ticket, $status);

        event(SupportTicketStatusChangedAudit::fromTicket($ticket, $previous, $actor));

        if ($status === SupportTicketStatus::Resolved && $ticket->customer) {
            $this->notifications->notifyCustomer(
                NotificationEventType::SupportTicketResolved,
                $ticket->customer,
                $this->notificationPayload($ticket),
                idempotencyKey: 'support_ticket_resolved:'.$ticket->id,
            );
        }

        return $ticket->fresh(['customer', 'order', 'assignedAdmin', 'messages']);
    }

    public function addAdminMessage(
        Admin $actor,
        SupportTicket $ticket,
        string $message,
        bool $waitingForCustomer = false,
    ): SupportTicket {
        $this->assertAdminCanAccess($actor, $ticket);

        return DB::transaction(function () use ($actor, $ticket, $message, $waitingForCustomer) {
            $this->addMessage($ticket, SupportMessageSenderType::Admin, $actor->id, $message);

            if ($ticket->status === SupportTicketStatus::New) {
                $this->transitionStatus($ticket, SupportTicketStatus::Open);
            } elseif ($ticket->status === SupportTicketStatus::Open) {
                $this->transitionStatus($ticket, SupportTicketStatus::InProgress);
            }

            if ($waitingForCustomer && $ticket->status !== SupportTicketStatus::WaitingCustomer) {
                $previous = $ticket->status?->value;
                $this->transitionStatus($ticket, SupportTicketStatus::WaitingCustomer);
                event(SupportTicketStatusChangedAudit::fromTicket($ticket, $previous ?? '', $actor));
            }

            $ticket->touch();

            if ($ticket->customer) {
                $this->notifications->notifyCustomer(
                    NotificationEventType::SupportReplyReceived,
                    $ticket->customer,
                    $this->notificationPayload($ticket),
                    idempotencyKey: 'support_reply:'.$ticket->id.':'.now()->timestamp,
                );
            }

            return $ticket->fresh(['customer', 'order', 'assignedAdmin', 'messages']);
        });
    }

    private function addMessage(
        SupportTicket $ticket,
        SupportMessageSenderType $senderType,
        ?string $senderId,
        string $message,
        ?array $attachments = null,
    ): SupportMessage {
        $record = SupportMessage::query()->create([
            'ticket_id' => $ticket->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $message,
            'attachments' => $attachments,
        ]);

        $actor = null;
        if ($senderType === SupportMessageSenderType::Customer && $senderId) {
            $actor = User::query()->find($senderId);
        } elseif ($senderType === SupportMessageSenderType::Admin && $senderId) {
            $actor = Admin::query()->find($senderId);
        }

        event(SupportMessageSentAudit::fromMessage($record, $actor));

        return $record;
    }

    private function transitionStatus(SupportTicket $ticket, SupportTicketStatus $status): void
    {
        $ticket->status = $status;

        if ($status === SupportTicketStatus::Resolved) {
            $ticket->resolved_at = now();
        }
        if ($status === SupportTicketStatus::Closed) {
            $ticket->closed_at = now();
        }
        if ($status === SupportTicketStatus::Reopened) {
            $ticket->resolved_at = null;
            $ticket->closed_at = null;
        }

        $ticket->save();
    }

    private function assertCustomerOwns(User $customer, SupportTicket $ticket): void
    {
        if ($ticket->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'ticket' => ['Support ticket not found.'],
            ]);
        }
    }

    private function assertAdminCanAccess(Admin $admin, SupportTicket $ticket): void
    {
        if ($admin->is_super_admin
            || $admin->hasAdminPermission(AdminPermissions::SUPPORT_MANAGE)) {
            return;
        }

        $ticket->loadMissing('order');
        $storeId = $ticket->order?->store_id;

        if ($storeId === null) {
            return;
        }

        $store = $ticket->order?->store;
        if ($store !== null && ! $this->storeContext->canView($admin, $store)) {
            throw ValidationException::withMessages([
                'ticket' => ['You do not have access to this store-scoped ticket.'],
            ]);
        }
    }

    private function applyAdminStoreScope(Admin $admin, $query): void
    {
        if ($admin->is_super_admin || $admin->hasAdminPermission(AdminPermissions::SUPPORT_MANAGE)) {
            return;
        }

        $assignedStoreIds = $admin->storeAssignments()
            ->where('is_active', true)
            ->pluck('store_id')
            ->all();

        if ($assignedStoreIds === []) {
            return;
        }

        $query->where(function ($q) use ($assignedStoreIds) {
            $q->whereDoesntHave('order')
                ->orWhereHas('order', function ($orderQuery) use ($assignedStoreIds) {
                    $orderQuery->whereIn('store_id', $assignedStoreIds)
                        ->orWhereNull('store_id');
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationPayload(SupportTicket $ticket): array
    {
        $ticket->loadMissing(['customer', 'order']);

        return [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status?->value,
            'category' => $ticket->category?->value,
            'order_number' => $ticket->order?->order_number,
            'customer_name' => $ticket->customer?->name,
        ];
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = 'SUP-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while (SupportTicket::query()->where('ticket_number', $number)->exists());

        return $number;
    }
}
