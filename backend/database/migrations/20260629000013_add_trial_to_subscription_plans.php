<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Optional trial period for a plan (amount 0 = free trial). */
final class AddTrialToSubscriptionPlans extends AbstractMigration
{
    public function change(): void
    {
        $this->table('subscription_plans')
            ->addColumn('trial_enabled', 'boolean', ['default' => false])
            ->addColumn('trial_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('trial_period_count', 'integer', ['null' => true])
            ->addColumn('trial_period_unit', 'string', ['limit' => 10, 'default' => 'days']) // days | weeks | months
            ->update();
    }
}
