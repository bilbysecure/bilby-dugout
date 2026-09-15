<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Policies\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Reusable roles with custom permissions. Generic over the model class so it
 * serves both StaffRole and ClientRole. Global-admin writes; agency read.
 */
final class RoleService
{
    private const FIELDS = ['name', 'slug', 'description', 'permissions', 'status'];

    /** @param class-string<Model> $model */
    public function list(Principal $p, string $model): array
    {
        if (!$p->isAgency()) {
            throw new AuthorizationException('Agency access required');
        }
        return $model::orderBy('name')->get()->toArray();
    }

    /** @param class-string<Model> $model */
    public function create(Principal $p, string $model, array $data): Model
    {
        $this->assertAdmin($p);
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (empty($payload['name'])) {
            throw new InvalidArgumentException('name is required');
        }
        $payload['slug'] = $this->slug($payload['slug'] ?? $payload['name']);
        $payload['permissions'] = array_values((array) ($payload['permissions'] ?? []));
        $payload['status'] = $payload['status'] ?? 'active';
        return $model::create($payload);
    }

    /** @param class-string<Model> $model */
    public function update(Principal $p, string $model, int $id, array $data): Model
    {
        $this->assertAdmin($p);
        $role = $model::find($id);
        if (!$role) {
            throw new ModelNotFoundException('Role not found');
        }
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (array_key_exists('permissions', $payload)) {
            $payload['permissions'] = array_values((array) $payload['permissions']);
        }
        if (!empty($payload['slug'])) {
            $payload['slug'] = $this->slug($payload['slug']);
        }
        $role->fill($payload)->save();
        return $role;
    }

    /** @param class-string<Model> $model */
    public function delete(Principal $p, string $model, int $id): void
    {
        $this->assertAdmin($p);
        $role = $model::find($id);
        if (!$role) {
            throw new ModelNotFoundException('Role not found');
        }
        $role->delete();
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
