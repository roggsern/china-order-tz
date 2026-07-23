<?php

namespace Tests\Unit\Ops;

use App\Support\Ops\Backup\BackupDatabaseClient;
use Tests\TestCase;

class BackupDatabaseClientTest extends TestCase
{
    public function test_ssl_arguments_default_to_disabled_for_internal_network(): void
    {
        config([
            'backup.database.ssl_mode' => 'DISABLED',
            'backup.database.ssl_ca' => null,
        ]);

        $this->assertSame(['--ssl-mode=DISABLED'], BackupDatabaseClient::sslArguments());
    }

    public function test_ssl_arguments_prefer_ca_when_configured(): void
    {
        config([
            'backup.database.ssl_mode' => 'DISABLED',
            'backup.database.ssl_ca' => '/etc/ssl/certs/mysql-ca.pem',
        ]);

        $this->assertSame(
            ['--ssl-mode=VERIFY_CA', '--ssl-ca=/etc/ssl/certs/mysql-ca.pem'],
            BackupDatabaseClient::sslArguments(),
        );
    }

    public function test_ssl_arguments_support_required_mode(): void
    {
        config([
            'backup.database.ssl_mode' => 'REQUIRED',
            'backup.database.ssl_ca' => null,
        ]);

        $this->assertSame(['--ssl-mode=REQUIRED'], BackupDatabaseClient::sslArguments());
    }
}
