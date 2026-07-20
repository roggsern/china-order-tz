<?php

namespace App\Enums;

enum NotificationDeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Read = 'read';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Read => 'Read',
        };
    }
}
