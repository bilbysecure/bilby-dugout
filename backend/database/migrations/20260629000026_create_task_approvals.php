<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The immutable per-decision audit for tasks (Master Spec §11 Layer B). Every
 * internal or client decision writes a row carrying the incrementing `round`, so
 * the full per-round history is preserved across re-loops.
 */
final class CreateTaskApprovals extends AbstractMigration
{
    public function change(): void
    {
        $this->table('task_approvals')
            ->addColumn('task_id', 'biginteger', ['signed' => false])
            ->addColumn('approver_id', 'string', ['limit' => 255])
            ->addColumn('approver_type', 'string', ['limit' => 40]) // internal_manager | client_owner
            ->addColumn('decision', 'string', ['limit' => 40])      // approve | request_changes
            ->addColumn('comment', 'text', ['null' => true])
            ->addColumn('signature', 'text', ['null' => true])
            ->addColumn('round', 'integer', ['default' => 1])
            ->addTimestamps()
            ->addIndex('task_id')
            ->addIndex(['task_id', 'round'])
            ->addForeignKey('task_id', 'tasks', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
