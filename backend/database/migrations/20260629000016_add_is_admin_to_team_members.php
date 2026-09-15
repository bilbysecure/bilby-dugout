<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Decouple the global-admin role from the "admin" designation. Role is now
 * driven by an explicit is_admin flag, so editing a member's job-title
 * designation can no longer silently demote them. Existing admins (by the
 * legacy designation) are backfilled to preserve current access.
 */
final class AddIsAdminToTeamMembers extends AbstractMigration
{
    public function up(): void
    {
        $this->table('team_members')
            ->addColumn('is_admin', 'boolean', ['default' => false])
            ->update();

        $this->execute("UPDATE team_members SET is_admin = 1 WHERE designation = 'admin'");
    }

    public function down(): void
    {
        $this->table('team_members')->removeColumn('is_admin')->update();
    }
}
