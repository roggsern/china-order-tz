<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\DB;

/**
 * Concurrency-safe customer code generation via locked sequence row.
 */
class CustomerCodeGenerator
{
    public function generate(): string
    {
        $prefix = (string) config('crm.customer_code_prefix', 'CTZ-CUS');
        $padding = max(1, (int) config('crm.customer_code_padding', 6));

        return DB::transaction(function () use ($prefix, $padding): string {
            $row = DB::table('customer_code_sequences')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('customer_code_sequences')->insert([
                    'id' => 1,
                    'last_value' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $next = 1;
            } else {
                $next = ((int) $row->last_value) + 1;
            }

            DB::table('customer_code_sequences')
                ->where('id', 1)
                ->update([
                    'last_value' => $next,
                    'updated_at' => now(),
                ]);

            return sprintf('%s-%s', $prefix, str_pad((string) $next, $padding, '0', STR_PAD_LEFT));
        });
    }
}
