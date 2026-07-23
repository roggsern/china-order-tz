<?php

namespace App\Support\Monitoring;

interface AlertNotifier
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(string $title, string $severity, array $context = []): void;
}
