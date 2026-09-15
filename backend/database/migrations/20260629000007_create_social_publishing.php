<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Social planning & publishing: channels, posts (+ per-channel targets),
 *  approvals, RSS sources, content-tag rules, and product catalog (IG tagging). */
final class CreateSocialPublishing extends AbstractMigration
{
    private const PLATFORMS = [
        'instagram', 'facebook', 'linkedin', 'twitter', 'tiktok', 'youtube', 'pinterest',
    ];

    public function change(): void
    {
        $this->table('social_channels')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('brand_kit_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('platform', 'string', ['limit' => 32])
            ->addColumn('account_name', 'string', ['limit' => 255])
            ->addColumn('external_account_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('avatar_url', 'string', ['limit' => 1024, 'null' => true])
            ->addColumn('timezone', 'string', ['limit' => 64, 'default' => 'UTC'])
            ->addColumn('status', 'string', ['default' => 'connected'])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('brand_kit_id')
            ->create();

        $this->table('products')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('brand_kit_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('currency', 'string', ['limit' => 8, 'default' => 'AUD'])
            ->addColumn('image_url', 'string', ['limit' => 1024, 'null' => true])
            ->addColumn('external_product_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('retailer_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('status', 'string', ['default' => 'active'])
            ->addTimestamps()
            ->addIndex('client_email')
            ->create();

        $this->table('scheduled_posts')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('brand_kit_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('title', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('caption', 'text', ['null' => true])
            ->addColumn('media', 'text', ['null' => true])            // [{url,type}]
            ->addColumn('link', 'string', ['limit' => 1024, 'null' => true])
            ->addColumn('first_comment', 'text', ['null' => true])
            ->addColumn('location_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('product_tags', 'text', ['null' => true])     // [{product_id,x,y,media_index}]
            ->addColumn('tags', 'text', ['null' => true])             // content tags/labels
            ->addColumn('status', 'string', ['default' => 'draft'])
            ->addColumn('scheduled_at', 'datetime', ['null' => true])
            ->addColumn('published_at', 'datetime', ['null' => true])
            ->addColumn('timezone', 'string', ['limit' => 64, 'default' => 'UTC'])
            ->addColumn('best_time_applied', 'boolean', ['default' => false])
            ->addColumn('rss_source_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('rss_guid', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('created_by_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_by_name', 'string', ['limit' => 255, 'null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('status')
            ->addIndex('scheduled_at')
            ->addIndex(['rss_source_id', 'rss_guid'])  // RSS dedupe
            ->create();

        $this->table('scheduled_post_targets')
            ->addColumn('scheduled_post_id', 'biginteger', ['signed' => false])
            ->addColumn('social_channel_id', 'biginteger', ['signed' => false])
            ->addColumn('platform', 'string', ['limit' => 32])
            ->addColumn('status', 'string', ['default' => 'pending'])
            ->addColumn('external_post_id', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('error', 'text', ['null' => true])
            ->addColumn('published_at', 'datetime', ['null' => true])
            ->addTimestamps()
            ->addIndex(['scheduled_post_id', 'social_channel_id'], ['unique' => true])
            ->addForeignKey('scheduled_post_id', 'scheduled_posts', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('post_approvals')
            ->addColumn('scheduled_post_id', 'biginteger', ['signed' => false])
            ->addColumn('decision', 'string', [])
            ->addColumn('note', 'text', ['null' => true])
            ->addColumn('signed_by_email', 'string', ['limit' => 255])
            ->addColumn('signed_by_name', 'string', ['limit' => 255, 'null' => true])
            ->addTimestamps()
            ->addIndex('scheduled_post_id')
            ->addForeignKey('scheduled_post_id', 'scheduled_posts', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('rss_sources')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('brand_kit_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('feed_url', 'string', ['limit' => 1024])
            ->addColumn('default_channel_ids', 'text', ['null' => true])
            ->addColumn('auto_schedule', 'boolean', ['default' => false])
            ->addColumn('default_status', 'string', ['default' => 'draft'])
            ->addColumn('apply_tag_rules', 'boolean', ['default' => true])
            ->addColumn('last_fetched_at', 'datetime', ['null' => true])
            ->addColumn('last_status', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('status', 'string', ['default' => 'active'])
            ->addTimestamps()
            ->addIndex('client_email')
            ->create();

        $this->table('content_tag_rules')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('match_type', 'string', [])
            ->addColumn('pattern', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('add_tags', 'text', ['null' => true])
            ->addColumn('add_channel_ids', 'text', ['null' => true])
            ->addColumn('priority', 'integer', ['default' => 0])
            ->addColumn('enabled', 'boolean', ['default' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->create();
    }
}
