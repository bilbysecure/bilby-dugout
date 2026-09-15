<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDirectory extends AbstractMigration
{
    public function change(): void
    {
        $this->table('team_members')
            ->addColumn('full_name', 'string', ['limit' => 255])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('designation', 'string', [])
            ->addColumn('staff_role_slug', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('department', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('bio', 'text', ['null' => true])
            ->addColumn('avatar_url', 'string', ['limit' => 1024, 'null' => true])
            ->addColumn('status', 'string', ['default' => 'active'])
            ->addTimestamps()
            ->addIndex('email', ['unique' => true])
            ->addIndex('designation')
            ->create();

        $this->table('client_members')
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('company_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('full_name', 'string', ['limit' => 255])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('role', 'string', ['limit' => 64, 'default' => 'Member'])
            ->addColumn('client_role_slug', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('avatar_url', 'string', ['limit' => 1024, 'null' => true])
            ->addColumn('status', 'string', ['default' => 'active'])
            ->addTimestamps()
            ->addIndex('client_email')
            ->addIndex('email', ['unique' => true])
            ->create();

        foreach (['client_roles', 'staff_roles'] as $rolesTable) {
            $this->table($rolesTable)
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('slug', 'string', ['limit' => 128])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('permissions', 'text', ['null' => true])
                ->addColumn('status', 'string', ['default' => 'active'])
                ->addTimestamps()
                ->addIndex('slug', ['unique' => true])
                ->create();
        }
    }
}
