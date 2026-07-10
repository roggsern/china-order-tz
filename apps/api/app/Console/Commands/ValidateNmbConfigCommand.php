<?php

namespace App\Console\Commands;

use App\Support\Nmb\NmbConfigValidator;
use Illuminate\Console\Command;

class ValidateNmbConfigCommand extends Command
{
    protected $signature = 'nmb:validate-config';

    protected $description = 'Validate NMB payment configuration for the current environment.';

    public function handle(NmbConfigValidator $validator): int
    {
        $errors = $validator->validate();

        if ($errors === []) {
            $this->info('NMB configuration is valid.');

            return self::SUCCESS;
        }

        foreach ($errors as $error) {
            $this->error($error);
        }

        return self::FAILURE;
    }
}
