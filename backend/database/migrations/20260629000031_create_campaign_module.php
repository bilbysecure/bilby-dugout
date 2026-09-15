<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Ad Campaign Project module (Master Spec §14) — productizes the 30-day /
 * 3-phase Meta lead-gen playbook on top of an `ad_campaign` project (Phase 3).
 * Read/report only: audiences are RECORDED, not created via API.
 */
final class CreateCampaignModule extends AbstractMigration
{
    public function change(): void
    {
        $this->table('campaigns')
            ->addColumn('project_id', 'biginteger', ['signed' => false])
            ->addColumn('client_email', 'string', ['limit' => 255])     // denormalized for scoping
            ->addColumn('meta_campaign_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('objective', 'string', ['limit' => 64, 'default' => 'lead_generation'])
            ->addColumn('total_budget', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
            ->addColumn('cpl_target', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('start_date', 'date', ['null' => true])
            ->addColumn('end_date', 'date', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'draft']) // draft|active|paused|completed
            ->addTimestamps()
            ->addIndex('project_id', ['unique' => true])
            ->addIndex('client_email')
            ->addIndex('status')
            ->create();

        $this->table('campaign_phases')
            ->addColumn('campaign_id', 'biginteger', ['signed' => false])
            ->addColumn('phase_number', 'integer', ['default' => 1])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('start_date', 'date', ['null' => true])
            ->addColumn('end_date', 'date', ['null' => true])
            ->addColumn('budget_split', 'decimal', ['precision' => 6, 'scale' => 2, 'null' => true]) // % of total
            ->addColumn('meta_adset_ids', 'text', ['null' => true])     // JSON
            ->addTimestamps()
            ->addIndex(['campaign_id', 'phase_number'])
            ->addForeignKey('campaign_id', 'campaigns', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('ad_variants')
            ->addColumn('phase_id', 'biginteger', ['signed' => false])
            ->addColumn('campaign_id', 'biginteger', ['signed' => false])
            ->addColumn('meta_ad_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('media_id', 'biginteger', ['signed' => false, 'null' => true]) // creative ref via media
            ->addColumn('headline', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('is_control', 'boolean', ['default' => false])
            ->addTimestamps()
            ->addIndex('phase_id')
            ->addIndex('campaign_id')
            ->addForeignKey('phase_id', 'campaign_phases', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('phase_audiences')
            ->addColumn('phase_id', 'biginteger', ['signed' => false])
            ->addColumn('campaign_id', 'biginteger', ['signed' => false])
            ->addColumn('audience_name', 'string', ['limit' => 255])
            ->addColumn('meta_audience_id', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('audience_type', 'string', ['limit' => 64, 'default' => 'custom']) // custom|lookalike|saved
            ->addColumn('notes', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex('phase_id')
            ->addForeignKey('phase_id', 'campaign_phases', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('campaign_metrics_daily')
            ->addColumn('campaign_id', 'biginteger', ['signed' => false])
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('entity_type', 'string', ['limit' => 16]) // campaign|adset|ad
            ->addColumn('entity_id', 'string', ['limit' => 191])  // Meta id
            ->addColumn('metric_date', 'string', ['limit' => 10]) // 'Y-m-d' string for exact upsert match
            ->addColumn('impressions', 'integer', ['default' => 0])
            ->addColumn('reach', 'integer', ['default' => 0])
            ->addColumn('clicks', 'integer', ['default' => 0])
            ->addColumn('leads', 'integer', ['default' => 0])
            ->addColumn('spend', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
            ->addColumn('ctr', 'decimal', ['precision' => 8, 'scale' => 4, 'default' => 0])
            ->addColumn('cpl', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('frequency', 'decimal', ['precision' => 8, 'scale' => 4, 'default' => 0])
            ->addColumn('raw', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex(['campaign_id', 'metric_date'])
            ->addIndex(['entity_type', 'entity_id', 'metric_date'], ['unique' => true]) // upsert key
            ->create();

        $this->table('campaign_alerts')
            ->addColumn('campaign_id', 'biginteger', ['signed' => false])
            ->addColumn('rule_type', 'string', ['limit' => 32]) // cpl_over_target|frequency_high|pacing_off
            ->addColumn('threshold', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('window_days', 'integer', ['null' => true])
            ->addColumn('enabled', 'boolean', ['default' => true])
            ->addColumn('status', 'string', ['limit' => 16, 'default' => 'ok']) // ok|triggered
            ->addColumn('last_message', 'text', ['null' => true])
            ->addColumn('last_triggered_at', 'datetime', ['null' => true])
            ->addTimestamps()
            ->addIndex('campaign_id')
            ->addForeignKey('campaign_id', 'campaigns', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
