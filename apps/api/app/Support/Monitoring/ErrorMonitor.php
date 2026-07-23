<?php

namespace App\Support\Monitoring;

use Throwable;

interface ErrorMonitor
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function capture(Throwable $throwable, array $context = []): void;
}
