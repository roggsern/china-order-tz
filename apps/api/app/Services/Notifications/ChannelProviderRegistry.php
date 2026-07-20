<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Services\Notifications\Contracts\NotificationChannelInterface;
use InvalidArgumentException;

/**
 * Resolves channel → provider. Providers are bound in the container, not hardcoded here.
 */
class ChannelProviderRegistry
{
    /** @var array<string, NotificationChannelInterface> */
    private array $providers = [];

    public function register(NotificationChannelInterface $provider): void
    {
        $this->providers[$provider->channel()] = $provider;
    }

    public function resolve(NotificationChannel|string $channel): NotificationChannelInterface
    {
        $key = $channel instanceof NotificationChannel ? $channel->value : $channel;

        if (! isset($this->providers[$key])) {
            throw new InvalidArgumentException("No notification provider registered for channel [{$key}].");
        }

        return $this->providers[$key];
    }

    /**
     * @return list<NotificationChannelInterface>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    public function has(NotificationChannel|string $channel): bool
    {
        $key = $channel instanceof NotificationChannel ? $channel->value : $channel;

        return isset($this->providers[$key]);
    }
}
