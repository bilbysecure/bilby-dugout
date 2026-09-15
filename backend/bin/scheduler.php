<?php

declare(strict_types=1);

/**
 * Recurring-job dispatcher (Master Spec §17.1). Invoke on a cadence from cron
 * or a systemd timer:
 *
 *   php bin/scheduler.php
 *
 * Enqueues the registered recurring jobs (stubs for now); the worker runs them.
 * Cadence is owned by the timer, not this script.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Bootstrap;
use App\Services\Jobs\Scheduler;

$container = Bootstrap::bootConsole();

/** @var Scheduler $scheduler */
$scheduler = $container->get(Scheduler::class);
$dispatched = $scheduler->dispatch();

fwrite(STDOUT, 'scheduler: enqueued ' . count($dispatched) . ' recurring job(s): ' . implode(', ', $dispatched) . "\n");
exit(0);
