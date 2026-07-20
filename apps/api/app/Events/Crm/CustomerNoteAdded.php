<?php

namespace App\Events\Crm;

use App\Models\Admin;
use App\Models\CustomerNote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerNoteAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CustomerNote $note,
        public readonly ?Admin $admin = null,
    ) {}
}
