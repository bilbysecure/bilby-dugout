<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddWebsiteToBrandKits extends AbstractMigration
{
    public function change(): void
    {
        $this->table('brand_kits')
            ->addColumn('website', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('guidelines_pdf_url', 'string', ['limit' => 1024, 'null' => true])
            ->update();
    }
}
