<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Media store (Master Spec §17.2). Every uploaded file is a row here, tenant-keyed
 * by `client_email` and attached polymorphically to its owner (brand asset,
 * request deliverable, …). `disk` + `path` are storage-driver internals and are
 * NEVER served raw — access is via short-lived signed URLs. Files stay
 * quarantined (scan_status != 'clean') until the async virus scan passes.
 */
final class CreateMedia extends AbstractMigration
{
    public function change(): void
    {
        $this->table('media')
            ->addColumn('client_email', 'string', ['limit' => 255, 'null' => true]) // tenant (null = agency-owned)
            ->addColumn('uploaded_by', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('disk', 'string', ['limit' => 32, 'default' => 'local'])
            ->addColumn('path', 'string', ['limit' => 1024])          // driver-internal key
            ->addColumn('original_name', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('mime', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('size', 'biginteger', ['signed' => false, 'default' => 0])
            ->addColumn('checksum', 'string', ['limit' => 64, 'null' => true]) // sha256
            ->addColumn('scan_status', 'string', ['limit' => 16, 'default' => 'pending']) // pending|clean|infected
            ->addColumn('attachable_type', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('attachable_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex(['attachable_type', 'attachable_id'])
            ->addIndex('scan_status')
            ->create();
    }
}
