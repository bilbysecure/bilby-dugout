<?php

declare(strict_types=1);

/**
 * Long-running queue worker (Master Spec §17.1). Run under systemd/supervisor:
 *
 *   php bin/worker.php [queue]      # default queue: "default"
 *
 * Reserves due jobs with FOR UPDATE SKIP LOCKED, so multiple workers are safe.
 * Requires MariaDB/MySQL (SQLite has no SKIP LOCKED).
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Bootstrap;
use App\Services\Jobs\Worker;
use Illuminate\Database\Capsule\Manager as DB;

$queue = $argv[1] ?? 'default';

$container = Bootstrap::bootConsole();

// Guard: reservation needs a driver with SKIP LOCKED.
$driver = DB::connection()->getDriverName();
if (!in_array($driver, ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "worker: reservation requires MariaDB/MySQL (FOR UPDATE SKIP LOCKED); current driver is '{$driver}'. Aborting.\n");
    exit(1);
}

/** @var Worker $worker */
$worker = $container->get(Worker::class);

// Graceful shutdown for systemd/supervisor (POSIX only; Windows dev loops without signals).
$running = true;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    $stop = function () use (&$running): void { $running = false; };
    pcntl_signal(SIGTERM, $stop);
    pcntl_signal(SIGINT, $stop);
}

fwrite(STDOUT, "worker: started on queue '{$queue}' (driver {$driver})\n");

while ($running) {
    try {
        $ran = $worker->runOnce($queue);
    } catch (\Throwable $e) {
        // Never let a reservation error kill the loop; back off briefly.
        fwrite(STDERR, 'worker: ' . $e->getMessage() . "\n");
        $ran = false;
    }
    if (!$ran) {
        sleep(1); // idle backoff when the queue is empty
    }
}

fwrite(STDOUT, "worker: stopped\n");
exit(0);
