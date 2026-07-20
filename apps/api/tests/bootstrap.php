<?php

/*
|--------------------------------------------------------------------------
| PHPUnit bootstrap — isolate from Compose MySQL env
|--------------------------------------------------------------------------
|
| Docker Compose exports APP_ENV=local and DB_CONNECTION=mysql into the
| container process. PHPUnit <env force="true"> is not always applied early
| enough for `php artisan test`. Force sqlite :memory: before autoload/boot.
|
*/

$testingEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'DB_HOST' => '',
    'DB_PORT' => '',
    'DB_USERNAME' => '',
    'DB_PASSWORD' => '',
];

foreach ($testingEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
