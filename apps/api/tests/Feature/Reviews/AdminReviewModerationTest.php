<?php

namespace Tests\Feature\Reviews;

use App\Enums\ActivityEventType;
use App\Enums\NotificationEventType;
use App\Enums\ReviewStatus;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
        $this->enableReviewsFeature();
    }

    private function enableReviewsFeature(): void
    {
        app(SettingsService::class)->set('features.flags', [
            'wishlist' => false,
            'reviews' => true,
            'new_checkout' => false,
        ]);
        Cache::flush();
    }

    private function reviewAdmin(): Admin
    {
        return Admin::factory()->withPermissions([
            AdminPermissions::REVIEWS_VIEW,
            AdminPermissions::REVIEWS_MANAGE,
        ])->create();
    }

    private function pendingReview(): Review
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        return Review::factory()->pending()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Great product',
            'comment' => 'Really happy with this purchase.',
        ]);
    }

    public function test_missing_review_permission_forbidden(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->getJson('/api/v1/admin/reviews')->assertForbidden();
    }

    public function test_admin_can_list_and_show_pending_reviews(): void
    {
        $review = $this->pendingReview();
        Sanctum::actingAs($this->reviewAdmin());

        $this->getJson('/api/v1/admin/reviews?status=pending')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $review->id)
            ->assertJsonPath('data.0.status', ReviewStatus::Pending->value);

        $this->getJson('/api/v1/admin/reviews/'.$review->id)
            ->assertOk()
            ->assertJsonPath('data.product.id', $review->product_id)
            ->assertJsonPath('data.customer.id', $review->user_id);
    }

    public function test_admin_can_approve_review_with_audit_and_notification(): void
    {
        $review = $this->pendingReview();
        Sanctum::actingAs($this->reviewAdmin());

        $this->postJson('/api/v1/admin/reviews/'.$review->id.'/approve', [
            'moderation_note' => 'Looks good.',
        ])->assertOk()
            ->assertJsonPath('data.status', ReviewStatus::Approved->value)
            ->assertJsonPath('data.is_approved', true);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_approved' => true,
            'status' => ReviewStatus::Approved->value,
            'moderation_note' => 'Looks good.',
        ]);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::ReviewApproved->value)
                ->where('subject_id', $review->id)
                ->exists(),
        );

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $review->user_id)
                ->where('event_type', NotificationEventType::ReviewApproved->value)
                ->exists(),
        );

        $this->getJson('/api/v1/products/'.$review->product->slug.'/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.id', $review->id);
    }

    public function test_admin_can_reject_review_with_audit_and_notification(): void
    {
        $review = $this->pendingReview();
        Sanctum::actingAs($this->reviewAdmin());

        $this->postJson('/api/v1/admin/reviews/'.$review->id.'/reject', [
            'moderation_note' => 'Contains promotional content.',
        ])->assertOk()
            ->assertJsonPath('data.status', ReviewStatus::Rejected->value)
            ->assertJsonPath('data.is_approved', false);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => ReviewStatus::Rejected->value,
            'moderation_note' => 'Contains promotional content.',
        ]);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::ReviewRejected->value)
                ->where('subject_id', $review->id)
                ->exists(),
        );

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $review->user_id)
                ->where('event_type', NotificationEventType::ReviewRejected->value)
                ->exists(),
        );

        $this->getJson('/api/v1/products/'.$review->product->slug.'/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_viewer_cannot_approve_or_reject(): void
    {
        $review = $this->pendingReview();

        Sanctum::actingAs(Admin::factory()->withPermissions([
            AdminPermissions::REVIEWS_VIEW,
        ])->create());

        $this->postJson('/api/v1/admin/reviews/'.$review->id.'/approve')->assertForbidden();
        $this->postJson('/api/v1/admin/reviews/'.$review->id.'/reject')->assertForbidden();
    }

    public function test_cannot_moderate_already_moderated_review(): void
    {
        $review = $this->pendingReview();
        Sanctum::actingAs($this->reviewAdmin());

        $this->postJson('/api/v1/admin/reviews/'.$review->id.'/approve')->assertOk();

        $this->postJson('/api/v1/admin/reviews/'.$review->id.'/reject')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['review']);
    }
}
