<?php

namespace App\Providers;

use App\Events\Audit\NotificationConfigurationUpdatedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Devices\ResolveActiveExpoPushTokens;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\Contracts\NotificationChannelInterface;
use App\Services\Notifications\Expo\ExpoPushClient;
use App\Services\Notifications\Expo\ExpoPushMessageBuilder;
use App\Services\Notifications\NotificationConfigurationResolver;
use App\Services\Notifications\NotificationConfigurationService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\NotificationRenderer;
use App\Services\Notifications\NotificationTemplateEngine;
use App\Services\Notifications\Providers\EmailNotificationProvider;
use App\Services\Notifications\Providers\InAppNotificationProvider;
use App\Services\Notifications\Providers\PushNotificationProvider;
use App\Services\Notifications\Providers\SMSNotificationProvider;
use App\Services\Notifications\Providers\WhatsAppNotificationProvider;
use App\Services\Notifications\WhatsApp\GhalaWebhookProcessor;
use App\Services\Notifications\WhatsApp\GhalaWebhookReplayGuard;
use App\Services\Notifications\WhatsApp\GhalaWebhookSignatureVerifier;
use App\Services\Notifications\WhatsApp\GhalaWhatsAppClient;
use App\Services\Notifications\WhatsApp\GhalaWhatsAppTemplateMapper;
use App\Services\Notifications\WhatsApp\PickupLocationResolver;
use App\Services\Notifications\WhatsApp\ShipmentDestinationResolver;
use App\Services\Notifications\WhatsApp\WhatsAppAmountFormatter;
use App\Services\Notifications\WhatsApp\WhatsAppDestinationPhone;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationRenderer::class);
        $this->app->singleton(NotificationTemplateEngine::class);
        $this->app->singleton(NotificationConfigurationResolver::class);
        $this->app->singleton(NotificationConfigurationService::class);
        $this->app->singleton(NotificationDispatcher::class);
        $this->app->singleton(NotificationPlatform::class);
        $this->app->singleton(WhatsAppAmountFormatter::class);
        $this->app->singleton(WhatsAppDestinationPhone::class);
        $this->app->singleton(PickupLocationResolver::class);
        $this->app->singleton(ShipmentDestinationResolver::class);
        $this->app->singleton(GhalaWhatsAppTemplateMapper::class);
        $this->app->singleton(GhalaWhatsAppClient::class);
        $this->app->singleton(GhalaWebhookSignatureVerifier::class);
        $this->app->singleton(GhalaWebhookReplayGuard::class);
        $this->app->singleton(GhalaWebhookProcessor::class);

        $this->app->singleton(InAppNotificationProvider::class);
        $this->app->singleton(EmailNotificationProvider::class);
        $this->app->singleton(WhatsAppNotificationProvider::class);
        $this->app->singleton(SMSNotificationProvider::class);
        $this->app->singleton(ResolveActiveExpoPushTokens::class);
        $this->app->singleton(ExpoPushMessageBuilder::class);
        $this->app->singleton(ExpoPushClient::class);
        $this->app->singleton(PushNotificationProvider::class);

        $this->app->singleton(ChannelProviderRegistry::class, function ($app): ChannelProviderRegistry {
            $registry = new ChannelProviderRegistry;

            /** @var list<class-string<NotificationChannelInterface>> $providers */
            $providers = [
                InAppNotificationProvider::class,
                EmailNotificationProvider::class,
                WhatsAppNotificationProvider::class,
                SMSNotificationProvider::class,
                PushNotificationProvider::class,
            ];

            foreach ($providers as $providerClass) {
                $registry->register($app->make($providerClass));
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        Event::listen(NotificationConfigurationUpdatedAudit::class, [RecordActivityLog::class, 'record']);
    }
}
