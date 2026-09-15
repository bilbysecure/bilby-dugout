<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Tasks with a dual-approval flow (Master Spec §11 Layer B). A task runs an
 * internal all-required-approvers gate, then (optionally) a single client-owner
 * gate. `current_round` drives the reset-on-re-loop rule: each re-entry to the
 * internal gate increments the round, so prior-round approvals no longer count.
 */
final class CreateTasks extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tasks')
            ->addColumn('project_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('title', 'string', ['limit' => 512])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('created_by', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('assigned_to', 'string', ['limit' => 255, 'null' => true])       // team member email
            ->addColumn('assigned_manager', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('requires_client_approval', 'boolean', ['default' => true])
            ->addColumn('status', 'string', ['limit' => 40, 'default' => 'draft'])
            ->addColumn('current_round', 'integer', ['default' => 0])                     // approval cycle counter
            ->addColumn('due_date', 'date', ['null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('project_id')
            ->addIndex('status')
            ->create();
    }
}
