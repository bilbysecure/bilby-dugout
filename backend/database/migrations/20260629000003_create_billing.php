<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBilling extends AbstractMigration
{
    public function change(): void
    {
        $this->table('subscription_plans')
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('services', 'text', ['null' => true])
            ->addColumn('monthly_request_limit', 'integer', ['default' => 0])
            ->addColumn('sla_tier', 'string', ['default' => 'burrow'])
            ->addColumn('brand_kit_allowance', 'integer', ['default' => 1])
            ->addColumn('concurrent_request_limit', 'integer', ['default' => 0])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('status', 'string', ['default' => 'active'])
            ->addTimestamps()
            ->create();

        $this->table('subscriptions')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('client_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('company_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('avatar_url', 'string', ['limit' => 1024, 'null' => true])
            ->addColumn('plan_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('plan_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('services', 'text', ['null' => true])
            ->addColumn('sla_tier', 'string', ['default' => 'burrow'])
            ->addColumn('status', 'string', ['default' => 'active'])
            ->addColumn('start_date', 'date', ['null' => true])
            ->addColumn('renewal_date', 'date', ['null' => true])
            ->addColumn('monthly_request_limit', 'integer', ['default' => 0])
            ->addColumn('account_manager_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('account_manager_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('account_managers', 'text', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('require_owner_approval', 'boolean', ['default' => false])
            ->addColumn('stripe_customer_id', 'string', ['limit' => 64, 'null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('status')
            ->create();

        $this->table('invoices')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('client_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('company_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('invoice_number', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('line_items', 'text', ['null' => true])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('currency', 'string', ['limit' => 8, 'default' => 'AUD'])
            ->addColumn('status', 'string', ['default' => 'draft'])
            ->addColumn('due_date', 'date', ['null' => true])
            ->addColumn('paid_date', 'date', ['null' => true])
            ->addColumn('subscription_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('request_ids', 'text', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('payment_link', 'string', ['limit' => 1024, 'null' => true])
            ->addColumn('file_url', 'string', ['limit' => 1024, 'null' => true])
            ->addColumn('stripe_customer_id', 'string', ['limit' => 64, 'null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('status')
            ->create();

        $this->table('quota_notifications')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('subscription_id', 'biginteger', ['signed' => false])
            ->addColumn('threshold', 'integer')
            ->addColumn('period_key', 'string', ['limit' => 7]) // YYYY-MM
            ->addColumn('request_count', 'integer', ['default' => 0])
            ->addTimestamps()
            ->addIndex(['subscription_id', 'threshold', 'period_key'], ['unique' => true])
            ->create();

        $this->table('client_onboarding_preferences')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('preferred_contact_method', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('turnaround_expectations', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('approval_contact_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('approval_contact_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex('client_email', ['unique' => true])
            ->create();
    }
}
