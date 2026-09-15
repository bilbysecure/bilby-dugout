<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Content-performance metrics per scheduled_post_target (Master Spec §12). A
 * scheduled job pulls engagement back from Meta after publishing and upserts by
 * (target, metric_date), keeping the raw provider response for audit.
 */
final class CreatePostMetrics extends AbstractMigration
{
    public function change(): void
    {
        $this->table('post_metrics')
            ->addColumn('scheduled_post_target_id', 'biginteger', ['signed' => false])
            ->addColumn('scheduled_post_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('platform', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('metric_date', 'date')
            ->addColumn('impressions', 'integer', ['default' => 0])
            ->addColumn('reach', 'integer', ['default' => 0])
            ->addColumn('engagement', 'integer', ['default' => 0])
            ->addColumn('likes', 'integer', ['default' => 0])
            ->addColumn('comments', 'integer', ['default' => 0])
            ->addColumn('shares', 'integer', ['default' => 0])
            ->addColumn('saves', 'integer', ['default' => 0])
            ->addColumn('clicks', 'integer', ['default' => 0])
            ->addColumn('raw', 'text', ['null' => true]) // raw Meta insights response
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('scheduled_post_id')
            ->addIndex(['scheduled_post_target_id', 'metric_date'], ['unique' => true]) // upsert key
            ->addForeignKey('scheduled_post_target_id', 'scheduled_post_targets', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
