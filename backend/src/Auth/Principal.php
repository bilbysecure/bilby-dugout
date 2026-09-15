<?php

declare(strict_types=1);

namespace App\Auth;

use App\Domain\Enums\Role;

/**
 * The authenticated caller, resolved from the app JWT by JwtAuthMiddleware.
 * This is the single object every policy and repository scopes against.
 */
final class Principal
{
    /**
     * @param string[] $permissions
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        public readonly string $name,
        public readonly Role $role,
        public readonly ?string $designation = null,
        public readonly ?string $clientEmail = null,
        public readonly ?string $clientRoleSlug = null,
        public readonly ?string $staffRoleSlug = null,
        public readonly array $permissions = [],
        public readonly ?string $impersonatedClientEmail = null,
    ) {
    }

    public function isAgency(): bool
    {
        return $this->role->isAgency();
    }

    public function isClient(): bool
    {
        return $this->role->isClient();
    }

    /** Manager-tier visibility: global admin, or an operations manager. */
    public function isManager(): bool
    {
        return $this->role === Role::GlobalAdmin || $this->designation === 'operations_manager';
    }

    public function can(string $permission): bool
    {
        return $this->role === Role::GlobalAdmin || in_array($permission, $this->permissions, true);
    }

    /** Build from decoded JWT claims. */
    public static function fromClaims(array $c): self
    {
        return new self(
            userId: (int) ($c['sub'] ?? 0),
            email: (string) ($c['email'] ?? ''),
            name: (string) ($c['name'] ?? ''),
            role: Role::from((string) ($c['role'] ?? Role::ClientMember->value)),
            designation: $c['designation'] ?? null,
            clientEmail: $c['client_email'] ?? null,
            clientRoleSlug: $c['client_role_slug'] ?? null,
            staffRoleSlug: $c['staff_role_slug'] ?? null,
            permissions: $c['permissions'] ?? [],
            impersonatedClientEmail: $c['impersonated_client_email'] ?? null,
        );
    }
}
