<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Durable job queue (Master Spec §17.1). Workers reserve one due row at a time
 * with FOR UPDATE SKIP LOCKED (MariaDB). `client_email` carries tenant context
 * into the handler so scoped work stays tenant-aware.
 */
final class CreateJobs extends AbstractMigration
{
    public function change(): void
    {
        $this->table('jobs')
            ->addColumn('queue', 'string', ['limit' => 128, 'default' => 'default'])
            ->addColumn('name', 'string', ['limit' => 191])            // handler key (JobRegistry)
            ->addColumn('payload', 'text', ['null' => true])           // JSON (array cast)
            ->addColumn('client_email', 'string', ['limit' => 255, 'null' => true]) // tenant context
            ->addColumn('available_at', 'datetime')                    // runnable-at (delay/backoff)
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('max_attempts', 'integer', ['default' => 3])
            ->addColumn('reserved_at', 'datetime', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'pending']) // pending|reserved
            ->addColumn('last_error', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex(['queue', 'status', 'available_at'])            // the reserve query
            ->addIndex('status')
            ->addIndex('reserved_at')
            ->addIndex('client_email')
            ->create();
    }
}
