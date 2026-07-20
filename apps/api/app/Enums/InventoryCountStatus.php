<?php

namespace App\Enums;

enum InventoryCountStatus: string
{
    case Draft = 'draft';
    case Counting = 'counting';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Cancelled = 'cancelled';
}
