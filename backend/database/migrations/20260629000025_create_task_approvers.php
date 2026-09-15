<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The internal approvers assigned to a task (Master Spec §11 Layer B).
 * ALL is_required approvers must sign off; optional ones may comment but don't
 * block. The single client owner is NOT stored here (implicit from the tenant).
 */
final class CreateTaskApprovers extends AbstractMigration
{
    public function change(): void
    {
        $this->table('task_approvers')
            ->addColumn('task_id', 'biginteger', ['signed' => false])
            ->addColumn('approver_id', 'string', ['limit' => 255])                 // team member email
            ->addColumn('approver_type', 'string', ['limit' => 40, 'default' => 'internal_manager'])
            ->addColumn('is_required', 'boolean', ['default' => true])
            ->addTimestamps()
            ->addIndex('task_id')
            ->addIndex(['task_id', 'approver_id'], ['unique' => true])
            ->addForeignKey('task_id', 'tasks', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
