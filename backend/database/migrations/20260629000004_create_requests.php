<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRequests extends AbstractMigration
{
    public function change(): void
    {
        $this->table('requests')
            ->addColumn('title', 'string', ['limit' => 512])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('type', 'string', [])
            ->addColumn('sub_type', 'text', ['null' => true])
            ->addColumn('platform', 'text', ['null' => true])
            ->addColumn('objective', 'string', ['null' => true])
            ->addColumn('target_audience', 'string', ['limit' => 1024, 'null' => true])
            ->addColumn('key_messaging', 'text', ['null' => true])
            ->addColumn('tone', 'string', ['null' => true])
            ->addColumn('priority', 'string', ['default' => 'normal'])
            ->addColumn('status', 'string', ['default' => 'submitted'])
            ->addColumn('due_date', 'date', ['null' => true])
            ->addColumn('publish_date', 'datetime', ['null' => true])
            ->addColumn('attachments', 'text', ['null' => true])
            ->addColumn('deliverables', 'text', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('submitted_by_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('submitted_by_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('feedback', 'text', ['null' => true])
            ->addColumn('brand_kit_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('brand_kit_name', 'string', ['limit' => 255, 'null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('status')
            ->addIndex('due_date')
            ->addIndex('publish_date')
            ->create();

        // Normalized assignees (decision #4) — authoritative source for "assigned to me".
        $this->table('request_assignees')
            ->addColumn('request_id', 'biginteger', ['signed' => false])
            ->addColumn('team_member_email', 'string', ['limit' => 255])
            ->addTimestamps()
            ->addIndex(['request_id', 'team_member_email'], ['unique' => true])
            ->addIndex('team_member_email')
            ->addForeignKey('request_id', 'requests', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
