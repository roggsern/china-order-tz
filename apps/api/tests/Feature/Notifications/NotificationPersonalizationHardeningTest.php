<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Mail\PlatformNotificationMail;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Auth\CustomerChangePasswordService;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\NotificationRenderer;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationPersonalizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        config([
            'notifications.email.configured' => true,
            'notifications.email.driver' => 'resend',
            'mail.default' => 'array',
            'mail.from.address' => 'orders@chinaordertz.com',
            'mail.from.name' => 'CHINA ORDER TZ',
        ]);
    }

    public function test_renderer_reports_unresolved_variable_names(): void
    {
        $renderer = new NotificationRenderer;
        $rendered = $renderer->render(
            'Hello {{customer_name}}, order {{order_number}}.',
            ['customer_name' => 'Asha'],
        );

        $this->assertSame('Hello Asha, order {{order_number}}.', $rendered);
        $this->assertSame(['order_number'], $renderer->unresolvedVariableNames($rendered));
    }

    public function test_enriches_customer_name_from_user_name_when_missing(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'name' => 'Asha Mwinyi',
            'first_name' => 'Ignored',
            'email' => 'asha@example.com',
        ]);
        $this->seedPasswordChangedEmailTemplate();

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PasswordChanged,
            $user,
            ['message' => 'smoke without name'],
            channels: [NotificationChannel::Email],
        );

        $notification = $created->first();
        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->status);
        $this->assertStringContainsString('Asha Mwinyi', (string) $notification->message);
        $this->assertStringNotContainsString('{{customer_name}}', (string) $notification->message);
        Mail::assertSent(PlatformNotificationMail::class, 1);
    }

    public function test_enriches_customer_name_from_first_name_when_name_empty(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'name' => '',
            'first_name' => 'Baraka',
            'email' => 'baraka@example.com',
        ]);
        $this->seedPasswordChangedEmailTemplate();

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PasswordChanged,
            $user,
            [],
            channels: [NotificationChannel::Email],
        );

        $this->assertStringContainsString('Baraka', (string) $created->first()->message);
        Mail::assertSent(PlatformNotificationMail::class, 1);
    }

    public function test_enriches_customer_name_generic_fallback(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'name' => '',
            'first_name' => '',
            'email' => 'anon@example.com',
        ]);
        $this->seedPasswordChangedEmailTemplate();

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PasswordChanged,
            $user,
            [],
            channels: [NotificationChannel::Email],
        );

        $this->assertStringContainsString('Hello Customer,', (string) $created->first()->message);
        Mail::assertSent(PlatformNotificationMail::class, 1);
    }

    public function test_preserves_explicit_customer_name(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'name' => 'Account Name',
            'email' => 'account@example.com',
        ]);
        $this->seedPasswordChangedEmailTemplate();

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PasswordChanged,
            $user,
            ['customer_name' => 'Explicit Alias'],
            channels: [NotificationChannel::Email],
        );

        $message = (string) $created->first()->message;
        $this->assertStringContainsString('Explicit Alias', $message);
        $this->assertStringNotContainsString('Account Name', $message);
        Mail::assertSent(PlatformNotificationMail::class, 1);
    }

    public function test_unresolved_non_enriched_token_blocks_email_send(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'name' => 'Asha',
            'email' => 'asha@example.com',
        ]);

        NotificationTemplate::factory()->create([
            'key' => 'order_created.email',
            'channel' => NotificationChannel::Email,
            'subject' => 'Order ready',
            'body' => 'Order {{order_number}} is ready for {{customer_name}}.',
            'is_active' => true,
        ]);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            [], // order_number intentionally missing; customer_name enriched
            channels: [NotificationChannel::Email],
        );

        $notification = $created->first();
        $this->assertSame(NotificationDeliveryStatus::Failed, $notification->status);
        $this->assertStringContainsString('Unresolved template variables', (string) $notification->error_message);
        $this->assertStringContainsString('{{order_number}}', (string) $notification->error_message);
        $this->assertStringNotContainsString('{{customer_name}}', (string) $notification->error_message);
        Mail::assertNothingSent();
    }

    public function test_in_app_still_delivers_when_email_would_have_unresolved_tokens(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Asha', 'email' => 'asha@example.com']);

        NotificationTemplate::factory()->create([
            'key' => 'order_created.email',
            'channel' => NotificationChannel::Email,
            'subject' => 'Order',
            'body' => 'Order {{order_number}} is ready.',
            'is_active' => true,
        ]);
        NotificationTemplate::factory()->create([
            'key' => 'order_created.in_app',
            'channel' => NotificationChannel::InApp,
            'subject' => 'Order',
            'body' => 'Order {{order_number}} is ready.',
            'is_active' => true,
        ]);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            [],
            channels: [NotificationChannel::InApp, NotificationChannel::Email],
            idempotencyKey: 'personalization-dual:'.$user->id,
        );

        $inApp = $created->first(fn ($n) => $n->channel === NotificationChannel::InApp);
        $email = $created->first(fn ($n) => $n->channel === NotificationChannel::Email);

        $this->assertSame(NotificationDeliveryStatus::Sent, $inApp->status);
        $this->assertSame(NotificationDeliveryStatus::Failed, $email->status);
        Mail::assertNothingSent();
    }

    public function test_password_changed_service_path_still_sends(): void
    {
        Mail::fake();
        app(SettingsService::class)->set('notifications.email_enabled', true);

        $user = User::factory()->create([
            'name' => 'Neema',
            'email' => 'neema@example.com',
            'password' => Hash::make('OldPassword1!'),
        ]);
        $this->seedPasswordChangedEmailTemplate();

        app(CustomerChangePasswordService::class)->change($user, [
            'current_password' => 'OldPassword1!',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        Mail::assertSent(PlatformNotificationMail::class, function (PlatformNotificationMail $mail) {
            return str_contains($mail->bodyText, 'Neema')
                && ! str_contains($mail->bodyText, '{{customer_name}}');
        });
    }

    public function test_commerce_order_created_happy_path_still_sends(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Asha', 'email' => 'asha@example.com']);

        NotificationTemplate::factory()->create([
            'key' => 'order_created.email',
            'channel' => NotificationChannel::Email,
            'subject' => 'Order {{order_number}}',
            'body' => 'Hello {{customer_name}}, order {{order_number}} total {{order_total}} {{currency}}.',
            'is_active' => true,
        ]);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            [
                'order_number' => 'ORD-9',
                'order_total' => '1000',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::Email],
        );

        $this->assertSame(NotificationDeliveryStatus::Sent, $created->first()->status);
        $this->assertStringContainsString('Hello Asha, order ORD-9', (string) $created->first()->message);
        Mail::assertSent(PlatformNotificationMail::class, 1);
    }

    private function seedPasswordChangedEmailTemplate(): void
    {
        NotificationTemplate::factory()->create([
            'key' => 'password_changed.email',
            'channel' => NotificationChannel::Email,
            'subject' => 'Your password was changed',
            'body' => 'Hello {{customer_name}}, your account password was changed successfully.',
            'is_active' => true,
        ]);
    }
}
