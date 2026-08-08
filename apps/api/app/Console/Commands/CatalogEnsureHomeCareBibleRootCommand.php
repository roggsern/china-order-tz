<?php

namespace App\Console\Commands;

use App\Services\Catalog\HomeCareCatalogBibleRootService;
use Illuminate\Console\Command;
use Throwable;

class CatalogEnsureHomeCareBibleRootCommand extends Command
{
    protected $signature = 'catalog:ensure-home-care-bible-root
                            {--execute : Create/normalize the Home Care CatalogBible root and re-parent children (default is dry-run)}';

    protected $description = 'Ensure China CatalogBible Home Care root category exists and parents Home Care department leaves under it.';

    public function handle(HomeCareCatalogBibleRootService $service): int
    {
        $dryRun = ! (bool) $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry run — no category rows will be updated. Pass --execute to apply.');
        }

        try {
            $result = $service->ensure(dryRun: $dryRun);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Home Care CatalogBible root plan' : 'Home Care CatalogBible root applied');
        $this->line('  Root id: '.($result['root_id'] ?? '—(will create)'));
        $this->line('  Root created: '.($result['root_created'] ? 'yes' : 'no'));

        foreach ($result['steps'] as $step) {
            $this->line('  - '.$step);
        }

        if ($result['reparented_slugs'] !== []) {
            $this->line('  Re-parented: '.implode(', ', $result['reparented_slugs']));
        }

        if ($result['already_attached_slugs'] !== []) {
            $this->line('  Already attached: '.implode(', ', $result['already_attached_slugs']));
        }

        if ($result['missing_child_slugs'] !== []) {
            $this->line('  Missing children: '.implode(', ', $result['missing_child_slugs']));
        }

        if ($dryRun) {
            $this->comment('Re-run with --execute to persist changes.');
        } else {
            $this->comment('Department Home Care was not created or modified.');
        }

        return self::SUCCESS;
    }
}
