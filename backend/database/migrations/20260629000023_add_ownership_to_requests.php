<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * A request belongs to EITHER a subscription (recurring plan work) OR a project
 * (one-off), never both (Master Spec §11 Layer A). Columns are added nullable so
 * existing rows are unaffected; a DB CHECK forbids the both-set corruption
 * (MariaDB), and RequestService enforces exactly-one on write. FKs are omitted
 * here to avoid a SQLite table rebuild of the FK-referenced `requests` table.
 */
final class AddOwnershipToRequests extends AbstractMigration
{
    public function up(): void
    {
        $this->table('requests')
            ->addColumn('subscription_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('project_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addIndex('subscription_id')
            ->addIndex('project_id')
            ->update();

        // DB-level guard (prod MariaDB). SQLite can't ADD a CHECK to an existing
        // table without a rebuild, so there the service is the sole enforcer.
        if ($this->adapter->getAdapterType() === 'mysql') {
            $this->execute(
                'ALTER TABLE requests ADD CONSTRAINT chk_request_owner ' .
                'CHECK (NOT (subscription_id IS NOT NULL AND project_id IS NOT NULL))'
            );
        }
    }

    public function down(): void
    {
        if ($this->adapter->getAdapterType() === 'mysql') {
            $this->execute('ALTER TABLE requests DROP CONSTRAINT chk_request_owner');
        }
        $this->table('requests')
            ->removeColumn('subscription_id')
            ->removeColumn('project_id')
            ->update();
    }
}
