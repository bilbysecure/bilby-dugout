<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Dead-letter table (Master Spec §17.1). A job that exhausts max_attempts is
 * moved here with its final error, then a notification is raised to admins.
 * Rows are replayable from the System / Jobs screen.
 */
final class CreateFailedJobs extends AbstractMigration
{
    public function change(): void
    {
        $this->table('failed_jobs')
            ->addColumn('queue', 'string', ['limit' => 128, 'default' => 'default'])
            ->addColumn('name', 'string', ['limit' => 191])
            ->addColumn('payload', 'text', ['null' => true])
            ->addColumn('client_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('error', 'text', ['null' => true])
            ->addColumn('failed_at', 'datetime')
            ->addTimestamps()
            ->addIndex('queue')
            ->addIndex('failed_at')
            ->create();
    }
}
