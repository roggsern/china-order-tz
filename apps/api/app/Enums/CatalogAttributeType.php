<?php

namespace App\Enums;

enum CatalogAttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case Select = 'select';
    case Multiselect = 'multiselect';

    public function requiresOptions(): bool
    {
        return $this === self::Select || $this === self::Multiselect;
    }
}
