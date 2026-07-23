<?php

namespace App\Enums;

/**
 * Resolved sell path for a Product (ADR 053).
 */
enum PurchasabilityPath: string
{
    case Simple = 'simple';
    case Variant = 'variant';
    case None = 'none';
}
