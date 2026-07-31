<?php

namespace Tests\Feature\Support;

use App\Enums\ActivityEventType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSupportTicketTest extends TestCase
{
    use RefreshDatabase;

    private function supportAdmin(): Admin
    {
        return Admin::factory()->withPermissions([
            AdminPermissions::SUPPORT_VIEW,
            AdminPermissions::SUPPORT_MANAGE,
            AdminPermissions::SUPPORT_ASSIGN,
        ])->create();
    }

    public function test_customer_can_create_and_view_own_ticket(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/account/support/tickets', [
            'subject' => 'Missing item',
            'category' => SupportTicketCategory::OrderIssue->value,
            'message' => 'My order arrived incomplete.',
        ])->assertCreated()
            ->assertJsonPath('data.status', SupportTicketStatus::New->value);

        $ticketId = $response->json('data.id');

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::SupportTicketCreated->value)
                ->where('subject_id', $ticketId)
                ->exists(),
        );

        $this->getJson('/api/v1/account/support/tickets/'.$ticketId)->assertOk();
        $this->getJson('/api/v1/account/support/tickets')->assertOk()
            ->assertJsonPath('success', true);

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/account/support/tickets/'.$ticketId)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ticket']);
    }

    public function test_customer_can_reply_on_open_ticket(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $ticketId = $this->postJson('/api/v1/account/support/tickets', [
            'subject' => 'Payment question',
            'category' => SupportTicketCategory::PaymentIssue->value,
            'message' => 'Was I charged twice?',
        ])->json('data.id');

        $this->postJson('/api/v1/account/support/tickets/'.$ticketId.'/messages', [
            'message' => 'Adding more detail here.',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::SupportMessageSent->value)
                ->exists(),
        );
    }

    public function test_admin_assign_status_and_reply(): void
    {
        $customer = User::factory()->create();
        $agent = $this->supportAdmin();
        $assignee = Admin::factory()->create();

        Sanctum::actingAs($customer);
        $ticketId = $this->postJson('/api/v1/account/support/tickets', [
            'subject' => 'Delivery delay',
            'category' => SupportTicketCategory::DeliveryIssue->value,
            'message' => 'Where is my package?',
        ])->json('data.id');

        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/admin/support/tickets')->assertOk();
        $this->getJson('/api/v1/admin/support/tickets/'.$ticketId)->assertOk();

        $this->postJson('/api/v1/admin/support/tickets/'.$ticketId.'/assign', [
            'admin_id' => $assignee->id,
        ])->assertOk()
            ->assertJsonPath('data.assigned_admin_id', $assignee->id);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::SupportTicketAssigned->value)
                ->where('subject_id', $ticketId)
                ->exists(),
        );

        $this->patchJson('/api/v1/admin/support/tickets/'.$ticketId.'/status', [
            'status' => SupportTicketStatus::InProgress->value,
        ])->assertOk();

        $this->postJson('/api/v1/admin/support/tickets/'.$ticketId.'/messages', [
            'message' => 'We are checking with the courier.',
            'waiting_for_customer' => true,
        ])->assertOk();

        $this->assertTrue(
            Notification::query()->where('user_id', $customer->id)->exists(),
        );
    }

    public function test_missing_support_permission_forbidden(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());
        $this->getJson('/api/v1/admin/support/tickets')->assertForbidden();
    }

    public function test_ticket_with_order_must_belong_to_customer(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/account/support/tickets', [
            'subject' => 'Not my order',
            'category' => SupportTicketCategory::OrderIssue->value,
            'order_id' => $order->id,
            'message' => 'Help',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);
    }
}
