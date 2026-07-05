<?php

namespace App\Actions\AdminPayments;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAdminPaymentsAction
{
    public function handle(): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['order'])
            ->latest()
            ->paginate(15);
    }
}
