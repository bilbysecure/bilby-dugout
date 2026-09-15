<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Auth\Principal;
use App\Domain\Models\ClientMember;
use App\Policies\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** A client company's team members (who can submit requests on the client's behalf). */
final class ClientMemberService
{
    private const FIELDS = ['full_name', 'email', 'role', 'client_role_slug', 'avatar_url', 'status', 'company_name', 'client_email'];

    public function list(Principal $p, string $clientEmail): array
    {
        if (!$p->isAgency()) {
            throw new AuthorizationException('Agency access required');
        }
        return ClientMember::where('client_email', $clientEmail)->orderBy('full_name')->get()->toArray();
    }

    public function create(Principal $p, array $data): ClientMember
    {
        $this->assertManager($p);
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (empty($payload['client_email'])) {
            throw new InvalidArgumentException('client_email is required');
        }
        if (empty($payload['full_name'])) {
            throw new InvalidArgumentException('full_name is required');
        }
        $payload['role'] = $payload['role'] ?: 'Member';
        $payload['status'] = $payload['status'] ?? 'active';
        $payload['email'] = empty($payload['email']) ? null : $payload['email'];
        return ClientMember::create($payload);
    }

    public function update(Principal $p, int $id, array $data): ClientMember
    {
        $this->assertManager($p);
        $member = ClientMember::find($id);
        if (!$member) {
            throw new ModelNotFoundException('Member not found');
        }
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        unset($payload['client_email']); // tenant can't be changed
        if (array_key_exists('email', $payload) && empty($payload['email'])) {
            $payload['email'] = null;
        }
        $member->fill($payload)->save();
        return $member;
    }

    public function delete(Principal $p, int $id): void
    {
        $this->assertManager($p);
        $member = ClientMember::find($id);
        if (!$member) {
            throw new ModelNotFoundException('Member not found');
        }
        $member->delete();
    }

    private function assertManager(Principal $p): void
    {
        if (!$p->isManager()) {
            throw new AuthorizationException('Manager access required');
        }
    }
}
