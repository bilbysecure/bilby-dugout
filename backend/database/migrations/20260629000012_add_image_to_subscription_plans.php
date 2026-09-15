<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Plan cover image, for the catalog / public / purchase pages (later use). */
final class AddImageToSubscriptionPlans extends AbstractMigration
{
    public function change(): void
    {
        $this->table('subscription_plans')
            ->addColumn('image_url', 'string', ['limit' => 1024, 'null' => true])
            ->update();
    }
}
