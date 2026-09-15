<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap. Spins up an isolated SQLite database, runs the real Phinx
 * migrations against it, and boots Eloquent — so model/DB tests run against the
 * actual schema (no hand-maintained test schema to drift).
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bilby_test_' . getmypid() . '_' . uniqid();
$dbFile = $base . '.sqlite3';
@unlink($dbFile);

// phinx.php + settings read these; set before Phinx loads the config.
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_SQLITE_NAME'] = $base;
putenv('DB_CONNECTION=sqlite');
putenv('DB_SQLITE_NAME=' . $base);

// Run the actual migrations via Phinx's own console command (sets up input opts).
$phinx = new PhinxApplication();
$phinx->setAutoExit(false);
$exit = $phinx->run(new ArrayInput([
    'command'         => 'migrate',
    '--configuration' => __DIR__ . '/../phinx.php',
    '--environment'   => 'default',
]), new NullOutput());

if ($exit !== 0) {
    fwrite(STDERR, "tests/bootstrap: migration failed (exit {$exit}). DB={$dbFile}\n");
    exit(1);
}

// Boot Eloquent on the migrated database for the models under test.
$capsule = new Capsule();
$capsule->addConnection([
    'driver'                  => 'sqlite',
    'database'                => $dbFile,
    'prefix'                  => '',
    'foreign_key_constraints' => true,
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

register_shutdown_function(static function () use ($dbFile): void {
    @unlink($dbFile);
});
