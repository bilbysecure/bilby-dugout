<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersAndAuth extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('full_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('azure_object_id', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('role', 'string', [
                'default' => 'client_member',
            ])
            ->addColumn('onboarding_completed', 'boolean', ['default' => false])
            ->addColumn('status', 'string', ['default' => 'active'])
            ->addTimestamps()
            ->addIndex('email', ['unique' => true])
            ->addIndex('azure_object_id', ['unique' => true])
            ->create();

        $this->table('refresh_tokens')
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('jti', 'string', ['limit' => 64])
            ->addColumn('expires_at', 'datetime')
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addTimestamps()
            ->addIndex('jti', ['unique' => true])
            ->addIndex('user_id')
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
