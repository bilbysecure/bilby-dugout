<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRequestActivity extends AbstractMigration
{
    public function change(): void
    {
        $this->table('request_approvals')
            ->addColumn('request_id', 'biginteger', ['signed' => false])
            ->addColumn('request_title', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('client_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('decision', 'string', [])
            ->addColumn('signed_by_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('signed_by_email', 'string', ['limit' => 255])
            ->addColumn('approval_note', 'text', ['null' => true])
            ->addColumn('digital_signature', 'string', ['limit' => 255])
            ->addColumn('deliverable_count', 'integer', ['null' => true])
            ->addTimestamps()
            ->addIndex('request_id')
            ->addForeignKey('request_id', 'requests', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('revision_requests')
            ->addColumn('request_id', 'biginteger', ['signed' => false])
            ->addColumn('deliverable_url', 'string', ['limit' => 1024])
            ->addColumn('deliverable_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('requested_by_email', 'string', ['limit' => 255])
            ->addColumn('requested_by_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('assigned_team_emails', 'text', ['null' => true])
            ->addColumn('summary', 'text')
            ->addColumn('status', 'string', ['default' => 'requested'])
            ->addColumn('response_note', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex('request_id')
            ->addForeignKey('request_id', 'requests', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('request_reviews')
            ->addColumn('request_id', 'biginteger', ['signed' => false])
            ->addColumn('request_title', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('client_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('rating', 'integer')
            ->addColumn('review', 'text', ['null' => true])
            ->addColumn('submitted_by_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('submitted_by_email', 'string', ['limit' => 255, 'null' => true])
            ->addTimestamps()
            ->addIndex('request_id')
            ->addForeignKey('request_id', 'requests', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('comments')
            ->addColumn('request_id', 'biginteger', ['signed' => false])
            ->addColumn('author_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('author_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('message', 'text')
            ->addColumn('is_internal', 'boolean', ['default' => false])
            ->addColumn('attachments', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex('request_id')
            ->addForeignKey('request_id', 'requests', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('notifications')
            ->addColumn('recipient_email', 'string', ['limit' => 255])
            ->addColumn('type', 'string', [])
            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('message', 'text')
            ->addColumn('request_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('comment_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('is_read', 'boolean', ['default' => false])
            ->addTimestamps()
            ->addIndex(['recipient_email', 'is_read'])
            ->create();

        $this->table('activity_logs')
            ->addColumn('request_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('actor_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('actor_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('action', 'string', ['limit' => 128])
            ->addColumn('from_status', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('to_status', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('entity_type', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('metadata', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex('request_id')
            ->addIndex('actor_email')
            ->create();

        $this->table('time_entries')
            ->addColumn('request_id', 'biginteger', ['signed' => false])
            ->addColumn('user_email', 'string', ['limit' => 255])
            ->addColumn('seconds', 'integer', ['default' => 0])
            ->addColumn('note', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('ended_at', 'datetime', ['null' => true])
            ->addTimestamps()
            ->addIndex('request_id')
            ->addForeignKey('request_id', 'requests', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
