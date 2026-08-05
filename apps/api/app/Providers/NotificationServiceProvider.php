<?php

namespace App\Providers;

use App\Events\Audit\NotificationConfigurationUpdatedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\Contracts\NotificationChannelInterface;
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
use App\Services\Notifications\WhatsApp\MetaWhatsAppTemplateMapper;
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
        $this->app->singleton(MetaWhatsAppTemplateMapper::class);

        $this->app->singleton(InAppNotificationProvider::class);
        $this->app->singleton(EmailNotificationProvider::class);
        $this->app->singleton(WhatsAppNotificationProvider::class);
        $this->app->singleton(SMSNotificationProvider::class);
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
