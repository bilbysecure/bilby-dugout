<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Client-facing reports (Master Spec §15). A generation job assembles data from
 * post_metrics (§12) and campaign_metrics (§14), renders a branded document
 * (BilbyPixel default or per-client white-label), and stores it as a media
 * record the client can download.
 */
final class CreateReports extends AbstractMigration
{
    public function change(): void
    {
        $this->table('reports')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('requested_by', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('type', 'string', ['limit' => 40]) // social_performance|campaign_wrapup|delivery_summary
            ->addColumn('period_start', 'date', ['null' => true])
            ->addColumn('period_end', 'date', ['null' => true])
            ->addColumn('params', 'text', ['null' => true]) // JSON
            ->addColumn('white_label', 'boolean', ['default' => false])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'pending']) // pending|processing|ready|failed
            ->addColumn('media_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('generated_at', 'datetime', ['null' => true])
            ->addColumn('error', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('status')
            ->addIndex(['client_email', 'type', 'period_start'])
            ->create();
    }
}
