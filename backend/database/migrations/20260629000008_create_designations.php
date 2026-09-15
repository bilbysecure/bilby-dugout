<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Manageable list of BilbyPixel staff designations (job titles). */
final class CreateDesignations extends AbstractMigration
{
    public function change(): void
    {
        $this->table('designations')
            ->addColumn('name', 'string', ['limit' => 128])
            ->addColumn('slug', 'string', ['limit' => 128])
            ->addColumn('description', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('status', 'string', ['default' => 'active'])
            ->addTimestamps()
            ->addIndex('slug', ['unique' => true])
            ->create();

        $now = date('Y-m-d H:i:s');
        $defaults = [
            ['Admin', 'admin'],
            ['Operations Manager', 'operations_manager'],
            ['Account Manager', 'account_manager'],
            ['Creative Director', 'creative_director'],
            ['Graphic Designer', 'graphic_designer'],
            ['Social Media Manager', 'social_media_manager'],
            ['Digital Marketing Specialist', 'digital_marketing_specialist'],
            ['Content Copywriter', 'content_copywriter'],
            ['Video Editor / Graphic Designer', 'video_editor_graphic_designer'],
        ];
        $rows = array_map(fn ($d) => [
            'name' => $d[0], 'slug' => $d[1], 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ], $defaults);

        $this->table('designations')->insert($rows)->saveData();
    }
}
