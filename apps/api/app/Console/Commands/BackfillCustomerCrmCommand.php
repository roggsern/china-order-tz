<?php

namespace App\Console\Commands;

use App\Services\Crm\CustomerProfileService;
use Illuminate\Console\Command;

class BackfillCustomerCrmCommand extends Command
{
    protected $signature = 'crm:backfill-customers';

    protected $description = 'Idempotently create CRM profiles and recalculate metrics for existing customer users';

    public function handle(CustomerProfileService $profiles): int
    {
        $this->info('Backfilling CRM customer profiles and metrics…');
        $result = $profiles->backfillExistingCustomers();
        $this->info(sprintf(
            'Done. Profiles created: %d. Metrics recalculated: %d.',
            $result['profiles_created'],
            $result['metrics_recalculated'],
        ));

        return self::SUCCESS;
    }
}
