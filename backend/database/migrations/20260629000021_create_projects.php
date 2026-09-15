<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * One-off projects (Master Spec §10). A project is a fixed-scope engagement
 * (e.g. an ad_campaign) sold via a quote → deposit → active flow, as opposed to
 * recurring subscription work. Tenant-keyed by `client_email`.
 */
final class CreateProjects extends AbstractMigration
{
    public function change(): void
    {
        $this->table('projects')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('title', 'string', ['limit' => 512])
            ->addColumn('type', 'string', ['limit' => 64, 'default' => 'ad_campaign'])
            ->addColumn('scope', 'text', ['null' => true])            // JSON
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('currency', 'string', ['limit' => 8, 'default' => 'AUD'])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'draft']) // draft|quoted|active|completed|cancelled
            ->addColumn('created_by', 'string', ['limit' => 255, 'null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('status')
            ->create();
    }
}
