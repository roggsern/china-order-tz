<?php

namespace App\Enums;

enum CustomerRegistrationSource: string
{
    case SelfRegistration = 'self_registration';
    case CheckoutRegistration = 'checkout_registration';
    case AdminCreated = 'admin_created';
    case Imported = 'imported';

    public function label(): string
    {
        return match ($this) {
            self::SelfRegistration => 'Self Registration',
            self::CheckoutRegistration => 'Checkout Registration',
            self::AdminCreated => 'Admin Created',
            self::Imported => 'Imported',
        };
    }
}
