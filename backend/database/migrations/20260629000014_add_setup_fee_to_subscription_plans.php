<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Optional one-time setup fee for a plan. */
final class AddSetupFeeToSubscriptionPlans extends AbstractMigration
{
    public function change(): void
    {
        $this->table('subscription_plans')
            ->addColumn('setup_fee_enabled', 'boolean', ['default' => false])
            ->addColumn('setup_fee_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->update();
    }
}
