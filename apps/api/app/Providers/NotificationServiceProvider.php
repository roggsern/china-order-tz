<?php

namespace App\Providers;

use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\Contracts\NotificationChannelInterface;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\NotificationRenderer;
use App\Services\Notifications\NotificationTemplateEngine;
use App\Services\Notifications\Providers\EmailNotificationProvider;
use App\Services\Notifications\Providers\InAppNotificationProvider;
use App\Services\Notifications\Providers\PushNotificationProvider;
use App\Services\Notifications\Providers\SMSNotificationProvider;
use App\Services\Notifications\Providers\WhatsAppNotificationProvider;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationRenderer::class);
        $this->app->singleton(NotificationTemplateEngine::class);
        $this->app->singleton(NotificationDispatcher::class);
        $this->app->singleton(NotificationPlatform::class);

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
}
