<?php

declare(strict_types=1);

/**
 * CLI entry point for scheduled tasks.
 *   php bin/console.php quota:check
 *   php bin/console.php approvals:remind
 *   php bin/console.php rss:ingest
 * Wire these into system cron (or a Node worker) on a schedule.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Bootstrap;
use App\Console\ApprovalsRemindCommand;
use App\Console\QuotaCheckCommand;
use App\Console\RssIngestCommand;

$commands = [
    'quota:check'      => QuotaCheckCommand::class,
    'approvals:remind' => ApprovalsRemindCommand::class,
    'rss:ingest'       => RssIngestCommand::class,
];

$name = $argv[1] ?? '';
if (!isset($commands[$name])) {
    fwrite(STDERR, "Usage: php bin/console.php <command>\nCommands: " . implode(', ', array_keys($commands)) . "\n");
    exit(1);
}

$container = Bootstrap::bootConsole();
exit((int) $container->get($commands[$name])->handle());
