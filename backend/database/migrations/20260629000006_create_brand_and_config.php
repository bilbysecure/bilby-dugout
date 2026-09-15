<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateBrandAndConfig extends AbstractMigration
{
    public function change(): void
    {
        $this->table('brand_kits')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('brand_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('tagline', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('industry', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('colors', 'text', ['null' => true])
            ->addColumn('typography', 'text', ['null' => true])
            ->addColumn('brand_voice_tone', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('brand_voice_personality', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('brand_voice_messaging', 'text', ['null' => true])
            ->addColumn('brand_voice_writing_guidelines', 'text', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex('client_email')
            ->create();

        $this->table('brand_assets')
            ->addColumn('brand_kit_id', 'biginteger', ['signed' => false])
            ->addColumn('client_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('category', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('logo_variant', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('file_url', 'string', ['limit' => 1024, 'null' => true])
            ->addTimestamps()
            ->addIndex('brand_kit_id')
            ->addIndex('client_email')
            ->addForeignKey('brand_kit_id', 'brand_kits', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('brand_asset_reviews')
            ->addColumn('brand_asset_id', 'biginteger', ['signed' => false])
            ->addColumn('brand_kit_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('client_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('asset_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('file_url', 'string', ['limit' => 1024, 'null' => true])
            ->addColumn('status', 'string', ['null' => true])
            ->addColumn('summary', 'text', ['null' => true])
            ->addColumn('score', 'float', ['null' => true])
            ->addColumn('matches', 'text', ['null' => true])
            ->addColumn('issues', 'text', ['null' => true])
            ->addColumn('recommendations', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex('brand_asset_id')
            ->addForeignKey('brand_asset_id', 'brand_assets', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('form_configs')
            ->addColumn('config_key', 'string', ['limit' => 128])
            ->addColumn('items', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex('config_key', ['unique' => true])
            ->create();

        $this->table('teams_comment_config')
            ->addColumn('enabled', 'boolean', ['default' => false])
            ->addColumn('team_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('channel_id', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('updated_by', 'string', ['limit' => 255, 'null' => true])
            ->addTimestamps()
            ->create();
    }
}
