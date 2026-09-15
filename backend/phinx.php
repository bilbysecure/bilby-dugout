<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

if (is_file(__DIR__ . '/.env')) {
    Dotenv::createImmutable(__DIR__)->safeLoad();
}

$connection = $_ENV['DB_CONNECTION'] ?? 'mysql';

// The application runs on MySQL/MariaDB only. The SQLite adapter below is
// reserved for the PHPUnit harness (tests/bootstrap.php), which migrates a
// throwaway file database per run; it is never used by the HTTP app or CLI.
$default = $connection === 'sqlite'
    ? [
        'adapter' => 'sqlite',
        'name'    => $_ENV['DB_SQLITE_NAME'] ?? (sys_get_temp_dir() . '/bilbydugout_test'),
        'suffix'  => '.sqlite3',
    ]
    : [
        'adapter' => 'mysql',
        'host'    => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'name'    => $_ENV['DB_DATABASE'] ?? 'bilbydugout',
        'user'    => $_ENV['DB_USERNAME'] ?? 'root',
        'pass'    => $_ENV['DB_PASSWORD'] ?? '',
        'port'    => (int) ($_ENV['DB_PORT'] ?? 3306),
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    ];

return [
    'paths' => [
        'migrations' => __DIR__ . '/database/migrations',
        'seeds'      => __DIR__ . '/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'default',
        'default'                 => $default,
    ],
    'version_order' => 'creation',
];
