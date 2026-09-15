<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Sample data for local smoke testing. Emails line up with the four roles so
 * `POST /auth/dev-login` (APP_ENV=local) resolves each one:
 *   admin@bilbypixel.com     → global_admin
 *   designer@bilbypixel.com  → agency_staff
 *   owner@acme.com           → client_owner
 *   member@acme.com          → client_member
 */
final class DevSeed extends AbstractSeed
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $day = fn (int $n) => date('Y-m-d', strtotime("$n days"));
        $dt  = fn (int $n) => date('Y-m-d H:i:s', strtotime("$n days"));
        $json = fn ($v) => json_encode($v);

        $this->table('team_members')->insert([
            ['full_name' => 'Ava Admin', 'email' => 'admin@bilbypixel.com', 'designation' => 'admin', 'department' => 'Operations', 'status' => 'active'],
            ['full_name' => 'Dana Designer', 'email' => 'designer@bilbypixel.com', 'designation' => 'graphic_designer', 'department' => 'Creative', 'status' => 'active'],
        ])->saveData();

        $this->table('client_members')->insert([
            ['client_email' => 'owner@acme.com', 'company_name' => 'Acme Co', 'full_name' => 'Olivia Owner', 'email' => 'owner@acme.com', 'role' => 'Owner', 'status' => 'active'],
            ['client_email' => 'owner@acme.com', 'company_name' => 'Acme Co', 'full_name' => 'Mark Member', 'email' => 'member@acme.com', 'role' => 'Member', 'status' => 'active'],
        ])->saveData();

        $this->table('subscription_plans')->insert([
            ['id' => 1, 'name' => 'Burrow', 'services' => $json(['social_media_post', 'graphic_design', 'video_production']), 'monthly_request_limit' => 20, 'sla_tier' => 'burrow', 'brand_kit_allowance' => 3, 'price' => 1999.00, 'status' => 'active'],
        ])->saveData();

        $this->table('subscriptions')->insert([
            ['client_email' => 'owner@acme.com', 'client_name' => 'Olivia Owner', 'company_name' => 'Acme Co', 'plan_id' => 1, 'plan_name' => 'Burrow', 'services' => $json(['social_media_post', 'graphic_design', 'video_production']), 'sla_tier' => 'burrow', 'status' => 'active', 'start_date' => $day(-90), 'renewal_date' => $day(275), 'monthly_request_limit' => 20, 'require_owner_approval' => 1, 'stripe_customer_id' => 'cus_DEMO123'],
        ])->saveData();

        $this->table('requests')->insert([
            ['id' => 1, 'title' => 'Q3 Instagram Campaign', 'description' => 'Launch creative for the Q3 push.', 'type' => 'social_media_post', 'platform' => $json(['instagram']), 'objective' => 'engagement', 'tone' => 'bold', 'priority' => 'high', 'status' => 'in_progress', 'due_date' => $day(7), 'publish_date' => $dt(10), 'client_email' => 'owner@acme.com', 'submitted_by_email' => 'owner@acme.com', 'submitted_by_name' => 'Olivia Owner'],
            ['id' => 2, 'title' => 'Brand Refresh Logo Pack', 'description' => 'Updated logo variants.', 'type' => 'graphic_design', 'priority' => 'normal', 'status' => 'review', 'due_date' => $day(3), 'publish_date' => $dt(5), 'client_email' => 'owner@acme.com', 'submitted_by_email' => 'owner@acme.com', 'submitted_by_name' => 'Olivia Owner'],
            ['id' => 3, 'title' => 'Website Homepage Update', 'type' => 'website_update', 'priority' => 'normal', 'status' => 'submitted', 'due_date' => $day(14), 'client_email' => 'owner@acme.com', 'submitted_by_email' => 'member@acme.com', 'submitted_by_name' => 'Mark Member'],
            ['id' => 4, 'title' => 'Holiday Promo Video', 'type' => 'video_production', 'priority' => 'high', 'status' => 'completed', 'publish_date' => $dt(-12), 'client_email' => 'owner@acme.com', 'submitted_by_email' => 'owner@acme.com', 'submitted_by_name' => 'Olivia Owner'],
            ['id' => 5, 'title' => 'LinkedIn Thought Leadership', 'type' => 'content_writing', 'priority' => 'low', 'status' => 'revision', 'due_date' => $day(9), 'client_email' => 'owner@acme.com', 'submitted_by_email' => 'owner@acme.com', 'submitted_by_name' => 'Olivia Owner'],
        ])->saveData();

        $this->table('request_assignees')->insert([
            ['request_id' => 1, 'team_member_email' => 'designer@bilbypixel.com'],
            ['request_id' => 2, 'team_member_email' => 'designer@bilbypixel.com'],
            ['request_id' => 4, 'team_member_email' => 'designer@bilbypixel.com'],
        ])->saveData();

        $this->table('comments')->insert([
            ['request_id' => 2, 'author_email' => 'designer@bilbypixel.com', 'author_name' => 'Dana Designer', 'message' => 'First draft is ready for your review.', 'is_internal' => 0],
            ['request_id' => 1, 'author_email' => 'designer@bilbypixel.com', 'author_name' => 'Dana Designer', 'message' => 'Internal: waiting on final copy.', 'is_internal' => 1],
        ])->saveData();

        $this->table('brand_kits')->insert([
            ['client_email' => 'owner@acme.com', 'brand_name' => 'Acme', 'tagline' => 'Build better', 'industry' => 'SaaS', 'colors' => $json([['role' => 'primary', 'hex' => '#2e5496']]), 'typography' => $json([['role' => 'heading', 'font_name' => 'Inter']]), 'brand_voice_tone' => 'professional'],
        ])->saveData();

        $this->table('invoices')->insert([
            ['client_email' => 'owner@acme.com', 'company_name' => 'Acme Co', 'invoice_number' => 'INV-0001', 'amount' => 1999.00, 'currency' => 'AUD', 'status' => 'paid', 'due_date' => $day(-30), 'paid_date' => $day(-28)],
            ['client_email' => 'owner@acme.com', 'company_name' => 'Acme Co', 'invoice_number' => 'INV-0002', 'amount' => 1999.00, 'currency' => 'AUD', 'status' => 'sent', 'due_date' => $day(15)],
        ])->saveData();

        $this->table('notifications')->insert([
            ['recipient_email' => 'designer@bilbypixel.com', 'type' => 'status_change', 'title' => 'New assignment', 'message' => 'You were assigned “Q3 Instagram Campaign”.', 'request_id' => 1, 'is_read' => 0],
            ['recipient_email' => 'owner@acme.com', 'type' => 'comment_mention', 'title' => 'New comment', 'message' => 'Dana commented on “Brand Refresh Logo Pack”.', 'request_id' => 2, 'is_read' => 0],
        ])->saveData();

        // ── Social planning & publishing ──────────────────────────────
        $this->table('social_channels')->insert([
            ['id' => 1, 'client_email' => 'owner@acme.com', 'platform' => 'instagram', 'account_name' => '@acmehq', 'timezone' => 'Australia/Sydney', 'status' => 'connected'],
            ['id' => 2, 'client_email' => 'owner@acme.com', 'platform' => 'facebook', 'account_name' => 'Acme Co', 'timezone' => 'Australia/Sydney', 'status' => 'connected'],
            ['id' => 3, 'client_email' => 'owner@acme.com', 'platform' => 'linkedin', 'account_name' => 'Acme', 'timezone' => 'Australia/Sydney', 'status' => 'connected'],
        ])->saveData();

        $this->table('products')->insert([
            ['client_email' => 'owner@acme.com', 'name' => 'Acme Widget', 'description' => 'Our flagship widget.', 'price' => 49.00, 'currency' => 'AUD', 'status' => 'active'],
        ])->saveData();

        $this->table('scheduled_posts')->insert([
            ['id' => 1, 'client_email' => 'owner@acme.com', 'title' => 'Feature launch', 'caption' => "New feature is live! #launch #product", 'tags' => $json(['launch']), 'status' => 'scheduled', 'scheduled_at' => $dt(2), 'timezone' => 'Australia/Sydney', 'created_by_email' => 'owner@acme.com', 'created_by_name' => 'Olivia Owner'],
            ['id' => 2, 'client_email' => 'owner@acme.com', 'title' => 'Behind the scenes', 'caption' => 'A peek behind the curtain at Acme.', 'tags' => $json([]), 'status' => 'pending_approval', 'timezone' => 'Australia/Sydney', 'created_by_email' => 'member@acme.com', 'created_by_name' => 'Mark Member'],
        ])->saveData();

        $this->table('scheduled_post_targets')->insert([
            ['scheduled_post_id' => 1, 'social_channel_id' => 1, 'platform' => 'instagram', 'status' => 'scheduled'],
            ['scheduled_post_id' => 1, 'social_channel_id' => 2, 'platform' => 'facebook', 'status' => 'scheduled'],
            ['scheduled_post_id' => 2, 'social_channel_id' => 3, 'platform' => 'linkedin', 'status' => 'pending'],
        ])->saveData();

        $this->table('content_tag_rules')->insert([
            ['client_email' => 'owner@acme.com', 'name' => 'Product launches', 'match_type' => 'keyword', 'pattern' => 'launch', 'add_tags' => $json(['product-launch', 'featured']), 'add_channel_ids' => $json([1]), 'priority' => 10, 'enabled' => 1],
        ])->saveData();

        $this->table('rss_sources')->insert([
            ['client_email' => 'owner@acme.com', 'name' => 'Company Blog', 'feed_url' => 'https://wordpress.org/news/feed/', 'default_channel_ids' => $json([1, 3]), 'auto_schedule' => 1, 'default_status' => 'draft', 'apply_tag_rules' => 1, 'status' => 'active'],
        ])->saveData();
    }
}
