<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * SLA tiers — named delivery windows (e.g. Hatch = 24h) that service plans
 * reference by slug and that later drive request delivery-deadline rules so
 * the delivery team knows the turnaround. Global-admin managed.
 */
final class CreateSlaTiers extends AbstractMigration
{
    public function change(): void
    {
        $this->table('sla_tiers')
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('slug', 'string', ['limit' => 128])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('duration_value', 'integer', ['default' => 24]) // 24/48/72 for hours, 1-4 for days
            ->addColumn('duration_unit', 'string', ['limit' => 16, 'default' => 'hours']) // hours | days
            ->addTimestamps()
            ->addIndex('slug', ['unique' => true])
            ->create();

        $now = date('Y-m-d H:i:s');
        $this->table('sla_tiers')->insert([
            ['name' => 'Hatch',  'slug' => 'hatch',  'description' => 'Fastest turnaround for urgent, priority work.', 'duration_value' => 24, 'duration_unit' => 'hours', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rapid',  'slug' => 'rapid',  'description' => 'Quick delivery for standard priority requests.',  'duration_value' => 48, 'duration_unit' => 'hours', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sprint', 'slug' => 'sprint', 'description' => 'Balanced turnaround for most requests.',           'duration_value' => 72, 'duration_unit' => 'hours', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Burrow', 'slug' => 'burrow', 'description' => 'Extended window for larger or complex projects.', 'duration_value' => 3,  'duration_unit' => 'days',  'created_at' => $now, 'updated_at' => $now],
        ])->saveData();
    }
}
