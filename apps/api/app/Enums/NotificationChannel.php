<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Push = 'push';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'In-App',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::Sms => 'SMS',
            self::Push => 'Push',
        };
    }
}
