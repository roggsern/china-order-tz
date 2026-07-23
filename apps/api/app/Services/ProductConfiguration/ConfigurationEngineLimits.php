<?php

namespace App\Services\ProductConfiguration;

/**
 * Code-backed engine limits for Configuration Template generation (Phase 1D-C).
 */
final class ConfigurationEngineLimits
{
    /**
     * Maximum Cartesian combinations allowed before generation is rejected.
     * Fail-closed with 422 — never partially generate.
     */
    public const MAX_CONFIGURATION_COMBINATIONS = 500;
}
