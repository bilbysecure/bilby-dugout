<?php

declare(strict_types=1);

namespace App\Auth;

use App\Domain\Enums\Role;
use App\Domain\Models\ClientMember;
use App\Domain\Models\RefreshToken;
use App\Domain\Models\StaffRole;
use App\Domain\Models\ClientRole;
use App\Domain\Models\TeamMember;
use App\Domain\Models\User;

/**
 * Orchestrates the backend-driven login (architecture §5.1):
 *   credentials → Azure AD (ROPC) → validate → upsert user → resolve scope → issue app JWTs.
 */
final class AuthService
{
    public function __construct(
        private readonly AzureAdClient $azure,
        private readonly JwtService $jwt,
        private readonly array $jwtCfg,
    ) {
    }

    /**
     * @return array{access_token:string,refresh_token:string,user:array}
     */
    public function login(string $username, string $password): array
    {
        // 1–3. Azure AD password grant + token validation
        $tokens = $this->azure->passwordGrant($username, $password);
        $claims = $this->azure->validateIdToken($tokens['id_token']);

        $email = strtolower((string) ($claims['preferred_username'] ?? $claims['email'] ?? $username));
        $oid   = (string) ($claims['oid'] ?? '');
        $name  = (string) ($claims['name'] ?? $email);

        return $this->issueSession($email, $name, $oid);
    }

    /**
     * DEV ONLY — issue a session by email without Azure AD (no password check).
     * The controller gates this to APP_ENV=local. Lets the stack be smoke-tested
     * without a live Azure tenant.
     */
    public function devLogin(string $email): array
    {
        $email = strtolower(trim($email));
        return $this->issueSession($email, null, '');
    }

    /** Local password login for client users (staff use Azure AD). */
    public function clientLogin(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (!$user || !$user->password_hash || !password_verify($password, (string) $user->password_hash)) {
            throw new AuthException('Invalid email or password', 401);
        }
        if (!$user->email_verified_at) {
            throw new AuthException('Please verify your email first', 403);
        }
        return $this->issueSession($email, $user->full_name, (string) ($user->azure_object_id ?? ''));
    }

    /** Issue a session for an already-authenticated user (e.g. right after invite acceptance). */
    public function sessionFor(string $email): array
    {
        return $this->issueSession(strtolower(trim($email)), null, '');
    }

    /** Upsert the user, resolve role/scope, and mint the app tokens. Shared by login + devLogin. */
    private function issueSession(string $email, ?string $name, string $oid): array
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->full_name = $name ?: ($user->full_name ?: $email);
        if ($oid !== '') {
            $user->azure_object_id = $oid;
        }
        [$role, $designation, $clientEmail, $clientRoleSlug, $staffRoleSlug, $permissions] =
            $this->resolveScope($email);
        $user->role = $role->value;
        $user->save();

        $access = $this->jwt->issueAccessToken([
            'sub'               => (string) $user->id,
            'email'             => $email,
            'name'              => $user->full_name,
            'role'              => $role->value,
            'designation'       => $designation,
            'client_email'      => $clientEmail,
            'client_role_slug'  => $clientRoleSlug,
            'staff_role_slug'   => $staffRoleSlug,
            'permissions'       => $permissions,
        ]);

        [$refresh, $jti, $exp] = $this->jwt->issueRefreshToken((int) $user->id);
        RefreshToken::create([
            'user_id'    => $user->id,
            'jti'        => $jti,
            'expires_at' => date('Y-m-d H:i:s', $exp),
        ]);

        return [
            'access_token'  => $access,
            'refresh_token' => $refresh,
            'user'          => [
                'id' => $user->id, 'email' => $email, 'name' => $user->full_name,
                'role' => $role->value, 'designation' => $designation,
                'client_email' => $clientEmail, 'permissions' => $permissions,
            ],
        ];
    }

    /** Rotate the access token from a valid, non-revoked refresh token. */
    public function refresh(string $refreshToken): array
    {
        $claims = $this->jwt->verify($refreshToken);
        if (($claims['typ'] ?? null) !== 'refresh') {
            throw new AuthException('Invalid refresh token', 401);
        }

        $record = RefreshToken::where('jti', $claims['jti'])->whereNull('revoked_at')->first();
        if (!$record) {
            throw new AuthException('Refresh token revoked or unknown', 401);
        }

        $user = User::find((int) $claims['sub']);
        if (!$user) {
            throw new AuthException('User not found', 401);
        }

        [$role, $designation, $clientEmail, $clientRoleSlug, $staffRoleSlug, $permissions] =
            $this->resolveScope($user->email);

        $access = $this->jwt->issueAccessToken([
            'sub' => (string) $user->id, 'email' => $user->email, 'name' => $user->full_name,
            'role' => $role->value, 'designation' => $designation, 'client_email' => $clientEmail,
            'client_role_slug' => $clientRoleSlug, 'staff_role_slug' => $staffRoleSlug,
            'permissions' => $permissions,
        ]);

        return ['access_token' => $access];
    }

    public function logout(string $refreshToken): void
    {
        try {
            $claims = $this->jwt->verify($refreshToken);
            RefreshToken::where('jti', $claims['jti'] ?? '')->update(['revoked_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            // logout is idempotent / best-effort
        }
    }

    /**
     * Derive role and scope from the directory tables.
     *
     * @return array{0:Role,1:?string,2:?string,3:?string,4:?string,5:string[]}
     */
    private function resolveScope(string $email): array
    {
        // Agency staff?
        $staff = TeamMember::where('email', $email)->where('status', 'active')->first();
        if ($staff) {
            // Role is driven by the explicit is_admin flag (legacy 'admin' designation
            // still honoured so pre-migration records keep working).
            $isAdmin = (bool) $staff->is_admin || $staff->designation === 'admin';
            $role = $isAdmin ? Role::GlobalAdmin : Role::AgencyStaff;
            $perms = $staff->staff_role_slug
                ? (StaffRole::where('slug', $staff->staff_role_slug)->first()?->permissions ?? [])
                : [];
            return [$role, $staff->designation, null, null, $staff->staff_role_slug, (array) $perms];
        }

        // Client member?
        $member = ClientMember::where('email', $email)->where('status', 'active')->first();
        if ($member) {
            $isOwner = strcasecmp((string) $member->role, 'Owner') === 0
                || strcasecmp((string) $member->client_email, $email) === 0;
            $role = $isOwner ? Role::ClientOwner : Role::ClientMember;
            $perms = $member->client_role_slug
                ? (ClientRole::where('slug', $member->client_role_slug)->first()?->permissions ?? [])
                : [];
            return [$role, null, $member->client_email, $member->client_role_slug, null, (array) $perms];
        }

        // Unknown directory entry → treat the login email as its own client tenant (owner).
        return [Role::ClientOwner, null, $email, null, null, []];
    }
}
