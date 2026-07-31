<?php

namespace App\Services\ConfigurationHealth\Contracts;

interface ConfigurationHealthCheck
{
    /**
     * @return list<array{group: string, status: string, message: string, severity: string}>
     */
    public function run(): array;
}
