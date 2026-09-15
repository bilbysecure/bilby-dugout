<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Meta integration keystone (Master Spec §16). One connection per client tenant,
 * holding an ENCRYPTED business/system-user token (never serialized) plus the
 * linked ad accounts / pages / IG accounts and granted scopes. meta_api_logs
 * powers integration-health so silent data gaps are visible.
 */
final class CreateMetaIntegration extends AbstractMigration
{
    public function change(): void
    {
        $this->table('meta_connections')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('token_encrypted', 'text', ['null' => true])   // AES-GCM ciphertext; hidden from API
            ->addColumn('token_expires_at', 'datetime', ['null' => true])
            ->addColumn('ad_account_ids', 'text', ['null' => true])    // JSON
            ->addColumn('page_ids', 'text', ['null' => true])          // JSON
            ->addColumn('ig_account_ids', 'text', ['null' => true])    // JSON
            ->addColumn('scopes_granted', 'text', ['null' => true])    // JSON
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'disconnected']) // disconnected|connected|error|expired
            ->addColumn('last_success_at', 'datetime', ['null' => true])
            ->addColumn('last_error', 'text', ['null' => true])
            ->addColumn('last_error_at', 'datetime', ['null' => true])
            ->addColumn('rate_limit_pct', 'integer', ['null' => true]) // highest observed usage %
            ->addTimestamps()
            ->addIndex('client_email', ['unique' => true])
            ->addIndex('status')
            ->create();

        $this->table('meta_api_logs')
            ->addColumn('meta_connection_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('endpoint', 'string', ['limit' => 512])
            ->addColumn('method', 'string', ['limit' => 8, 'default' => 'GET'])
            ->addColumn('http_status', 'integer', ['null' => true])
            ->addColumn('ok', 'boolean', ['default' => false])
            ->addColumn('error_code', 'integer', ['null' => true])
            ->addColumn('error_subcode', 'integer', ['null' => true])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('usage_pct', 'integer', ['null' => true])
            ->addTimestamps()
            ->addIndex(['client_email', 'created_at'])
            ->addIndex('ok')
            ->create();
    }
}
