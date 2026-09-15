<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Data-governance tables (Master Spec §17.4):
 *  - data_exports:      tenant data-export requests → downloadable media archive
 *  - consent_records:   accepted privacy/terms version per user (signup + quote accept)
 *  - account_deletions: soft-delete → grace period → hard-purge lifecycle (manager-initiated)
 */
final class CreateGovernance extends AbstractMigration
{
    public function change(): void
    {
        $this->table('data_exports')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('requested_by', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'pending']) // pending|processing|ready|failed
            ->addColumn('media_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('error', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('status')
            ->create();

        $this->table('consent_records')
            ->addColumn('user_email', 'string', ['limit' => 255])
            ->addColumn('client_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('policy_type', 'string', ['limit' => 32])   // privacy | terms
            ->addColumn('version', 'string', ['limit' => 64])
            ->addColumn('context', 'string', ['limit' => 64])       // signup | quote_acceptance
            ->addColumn('accepted_at', 'datetime')
            ->addColumn('ip_address', 'string', ['limit' => 64, 'null' => true])
            ->addTimestamps()
            ->addIndex('user_email')
            ->addIndex(['policy_type', 'version'])
            ->create();

        $this->table('account_deletions')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('requested_by', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'pending_purge']) // pending_purge|purged|cancelled
            ->addColumn('grace_days', 'integer', ['default' => 30])
            ->addColumn('soft_deleted_at', 'datetime')
            ->addColumn('purge_after', 'datetime')
            ->addColumn('purged_at', 'datetime', ['null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('status')
            ->create();
    }
}
