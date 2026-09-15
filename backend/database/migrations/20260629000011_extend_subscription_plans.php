<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Service-plan builder fields: pricing model, badge, default flag, description. */
final class ExtendSubscriptionPlans extends AbstractMigration
{
    public function change(): void
    {
        $this->table('subscription_plans')
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('badge_color', 'string', ['limit' => 16, 'null' => true])
            ->addColumn('is_default', 'boolean', ['default' => false])
            ->addColumn('pricing_type', 'string', ['limit' => 20, 'default' => 'standard']) // standard | time_based | credit_based
            ->addColumn('billing_period', 'string', ['limit' => 20, 'default' => 'monthly']) // weekly|monthly|quarterly|biannually|annually
            ->addColumn('hours', 'integer', ['null' => true])
            ->addColumn('credits', 'integer', ['null' => true])
            ->update();
    }
}
