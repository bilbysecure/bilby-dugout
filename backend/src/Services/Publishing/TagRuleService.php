<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Auth\Principal;
use App\Domain\Models\ContentTagRule;
use App\Support\TenantScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** CRUD for content-tag automation rules (applied by TagRuleEngine). */
final class TagRuleService
{
    private const FIELDS = ['name', 'match_type', 'pattern', 'add_tags', 'add_channel_ids', 'priority', 'enabled'];
    private const MATCH_TYPES = ['keyword', 'hashtag', 'platform', 'all'];

    public function list(Principal $p): array
    {
        return TenantScope::apply(ContentTagRule::query(), $p)->orderByDesc('priority')->get()->toArray();
    }

    public function create(Principal $p, array $data): ContentTagRule
    {
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (empty($payload['name'])) {
            throw new InvalidArgumentException('name is required');
        }
        if (empty($payload['match_type']) || !in_array($payload['match_type'], self::MATCH_TYPES, true)) {
            throw new InvalidArgumentException('valid match_type is required');
        }
        $payload['client_email'] = TenantScope::tenantFor($p, $data['client_email'] ?? null);
        return ContentTagRule::create($payload);
    }

    public function update(Principal $p, int $id, array $data): ContentTagRule
    {
        $rule = $this->findScoped($p, $id);
        $rule->fill(array_intersect_key($data, array_flip(self::FIELDS)))->save();
        return $rule;
    }

    public function delete(Principal $p, int $id): void
    {
        $this->findScoped($p, $id)->delete();
    }

    private function findScoped(Principal $p, int $id): ContentTagRule
    {
        $rule = TenantScope::apply(ContentTagRule::query(), $p)->whereKey($id)->first();
        if (!$rule) {
            throw new ModelNotFoundException('Rule not found');
        }
        return $rule;
    }
}
