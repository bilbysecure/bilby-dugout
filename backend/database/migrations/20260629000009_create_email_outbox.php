<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Simulated email/Teams "outbox" — where mock integrations record what they would send. */
final class CreateEmailOutbox extends AbstractMigration
{
    public function change(): void
    {
        $this->table('email_outbox')
            ->addColumn('channel', 'string', ['limit' => 32, 'default' => 'email']) // email | teams
            ->addColumn('recipient', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('subject', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('body', 'text', ['null' => true])
            ->addColumn('type', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('status', 'string', ['default' => 'simulated'])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex('channel')
            ->create();
    }
}
