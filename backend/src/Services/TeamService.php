<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\TeamMember;
use App\Policies\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** BilbyPixel staff directory + management (global-admin writes). */
final class TeamService
{
    private const FIELDS = ['full_name', 'email', 'phone', 'designation', 'staff_role_slug', 'department', 'bio', 'avatar_url', 'status', 'is_admin'];

    public function list(Principal $p): array
    {
        if (!$p->isAgency()) {
            throw new AuthorizationException('Agency access required');
        }
        return TeamMember::orderBy('full_name')->get()->toArray();
    }

    public function create(Principal $p, array $data): TeamMember
    {
        $this->assertAdmin($p);
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (empty($payload['full_name'])) {
            throw new InvalidArgumentException('full_name is required');
        }
        $payload['status'] = $payload['status'] ?? 'active';
        $payload['email'] = empty($payload['email']) ? null : $payload['email']; // avoid unique('') clash
        return TeamMember::create($payload);
    }

    public function update(Principal $p, int $id, array $data): TeamMember
    {
        $this->assertAdmin($p);
        $member = TeamMember::find($id);
        if (!$member) {
            throw new ModelNotFoundException('Team member not found');
        }
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (array_key_exists('email', $payload) && empty($payload['email'])) {
            $payload['email'] = null;
        }
        $member->fill($payload)->save();
        return $member;
    }

    public function delete(Principal $p, int $id): void
    {
        $this->assertAdmin($p);
        $member = TeamMember::find($id);
        if (!$member) {
            throw new ModelNotFoundException('Team member not found');
        }
        $member->delete();
    }

    private function assertAdmin(Principal $p): void
    {
        if ($p->role !== Role::GlobalAdmin) {
            throw new AuthorizationException('Global admin access required');
        }
    }
}
