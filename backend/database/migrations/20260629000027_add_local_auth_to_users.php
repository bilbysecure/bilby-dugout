<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Local auth for client users (Master Spec §17.3). Staff sign in via Azure AD;
 * client owners/members set a local password through the invite flow and verify
 * their email. Columns are nullable so existing (SSO) users are unaffected.
 */
final class AddLocalAuthToUsers extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('email_verified_at', 'datetime', ['null' => true])
            ->update();
    }
}
