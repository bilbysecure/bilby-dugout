<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Auth\Principal;
use App\Domain\Models\SocialChannel;
use App\Support\TenantScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** Connected social accounts (channels), grouped by brand. */
final class ChannelService
{
    private const FIELDS = ['brand_kit_id', 'platform', 'account_name', 'external_account_id', 'avatar_url', 'timezone', 'status'];
    private const PLATFORMS = ['instagram', 'facebook', 'linkedin', 'twitter', 'tiktok', 'youtube', 'pinterest'];

    public function list(Principal $p): array
    {
        return TenantScope::apply(SocialChannel::query(), $p)->orderBy('account_name')->get()->toArray();
    }

    public function create(Principal $p, array $data): SocialChannel
    {
        $payload = $this->pick($data);
        if (empty($payload['platform']) || !in_array($payload['platform'], self::PLATFORMS, true)) {
            throw new InvalidArgumentException('valid platform is required');
        }
        if (empty($payload['account_name'])) {
            throw new InvalidArgumentException('account_name is required');
        }
        $payload['client_email'] = TenantScope::tenantFor($p, $data['client_email'] ?? null);
        return SocialChannel::create($payload);
    }

    public function update(Principal $p, int $id, array $data): SocialChannel
    {
        $channel = $this->findScoped($p, $id);
        $channel->fill($this->pick($data))->save();
        return $channel;
    }

    public function delete(Principal $p, int $id): void
    {
        $this->findScoped($p, $id)->delete();
    }

    private function findScoped(Principal $p, int $id): SocialChannel
    {
        $channel = TenantScope::apply(SocialChannel::query(), $p)->whereKey($id)->first();
        if (!$channel) {
            throw new ModelNotFoundException('Channel not found');
        }
        return $channel;
    }

    private function pick(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FIELDS));
    }
}
