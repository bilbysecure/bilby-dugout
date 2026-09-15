<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\SlaTier;
use App\Policies\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * SLA tiers — named delivery windows referenced by service plans (by slug)
 * and, later, by request delivery-deadline rules. Global-admin writes;
 * agency read. Allowed windows: 24/48/72 hours or 1-4 days.
 */
final class SlaTierService
{
    private const FIELDS = ['name', 'slug', 'description', 'duration_value', 'duration_unit'];
    private const ALLOWED = ['hours' => [24, 48, 72], 'days' => [1, 2, 3, 4]];

    public function list(Principal $p): array
    {
        if (!$p->isAgency()) {
            throw new AuthorizationException('Agency access required');
        }
        $tiers = SlaTier::get()->toArray();
        usort($tiers, fn ($a, $b) => $this->hours($a) <=> $this->hours($b)); // fastest first
        return $tiers;
    }

    public function create(Principal $p, array $data): SlaTier
    {
        $this->assertAdmin($p);
        $payload = $this->clean($data);
        if (empty($payload['name'])) {
            throw new InvalidArgumentException('name is required');
        }
        $payload['slug'] = $this->slug($payload['slug'] ?? $payload['name']);
        return SlaTier::create($payload);
    }

    public function update(Principal $p, int $id, array $data): SlaTier
    {
        $this->assertAdmin($p);
        $tier = SlaTier::find($id);
        if (!$tier) {
            throw new ModelNotFoundException('SLA tier not found');
        }
        $payload = $this->clean($data);
        if (!empty($payload['slug'])) {
            $payload['slug'] = $this->slug($payload['slug']);
        }
        $tier->fill($payload)->save();
        return $tier;
    }

    public function delete(Principal $p, int $id): void
    {
        $this->assertAdmin($p);
        $tier = SlaTier::find($id);
        if (!$tier) {
            throw new ModelNotFoundException('SLA tier not found');
        }
        $tier->delete();
    }

    private function clean(array $data): array
    {
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (array_key_exists('duration_value', $payload)) {
            $payload['duration_value'] = (int) $payload['duration_value'];
        }
        $unit = $payload['duration_unit'] ?? null;
        if ($unit !== null && !array_key_exists($unit, self::ALLOWED)) {
            throw new InvalidArgumentException('duration_unit must be hours or days');
        }
        if ($unit !== null && array_key_exists('duration_value', $payload)
            && !in_array($payload['duration_value'], self::ALLOWED[$unit], true)) {
            throw new InvalidArgumentException(
                $unit === 'hours' ? 'Hours must be 24, 48 or 72' : 'Days must be 1-4'
            );
        }
        return $payload;
    }

    /** Normalise any tier to hours for consistent ordering. */
    private function hours(array $t): int
    {
        $v = (int) ($t['duration_value'] ?? 0);
        return ($t['duration_unit'] ?? 'hours') === 'days' ? $v * 24 : $v;
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
