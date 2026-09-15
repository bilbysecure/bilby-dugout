<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\StaffDesignation;
use App\Policies\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** Manage the list of staff designations (global-admin writes; agency read). */
final class DesignationService
{
    private const FIELDS = ['name', 'slug', 'description', 'status'];

    public function list(Principal $p): array
    {
        if (!$p->isAgency()) {
            throw new AuthorizationException('Agency access required');
        }
        return StaffDesignation::orderBy('name')->get()->toArray();
    }

    public function create(Principal $p, array $data): StaffDesignation
    {
        $this->assertAdmin($p);
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (empty($payload['name'])) {
            throw new InvalidArgumentException('name is required');
        }
        $payload['slug'] = $this->slug($payload['slug'] ?? $payload['name']);
        $payload['status'] = $payload['status'] ?? 'active';
        return StaffDesignation::create($payload);
    }

    public function update(Principal $p, int $id, array $data): StaffDesignation
    {
        $this->assertAdmin($p);
        $d = StaffDesignation::find($id);
        if (!$d) {
            throw new ModelNotFoundException('Designation not found');
        }
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (!empty($payload['slug'])) {
            $payload['slug'] = $this->slug($payload['slug']);
        }
        $d->fill($payload)->save();
        return $d;
    }

    public function delete(Principal $p, int $id): void
    {
        $this->assertAdmin($p);
        $d = StaffDesignation::find($id);
        if (!$d) {
            throw new ModelNotFoundException('Designation not found');
        }
        $d->delete();
    }

    private function slug(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($s)), '_');
    }

    private function assertAdmin(Principal $p): void
    {
        if ($p->role !== Role::GlobalAdmin) {
            throw new AuthorizationException('Global admin access required');
        }
    }
}
