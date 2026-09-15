<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\BrandAsset;
use App\Domain\Models\BrandAssetReview;
use App\Domain\Models\BrandKit;
use App\Domain\Models\Subscription;
use App\Domain\Models\SubscriptionPlan;
use App\Policies\AuthorizationException;
use App\Repositories\BrandKitRepository;
use App\Services\Ai\LlmService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** Multi-brand asset management + AI brand-consistency review. */
final class BrandService
{
    private const KIT_FIELDS = [
        'brand_name', 'tagline', 'industry', 'website', 'colors', 'typography',
        'brand_voice_tone', 'brand_voice_personality', 'brand_voice_messaging',
        'brand_voice_writing_guidelines', 'guidelines_pdf_url', 'notes',
    ];

    private const ASSET_FIELDS = ['name', 'category', 'description', 'logo_variant', 'file_url'];

    public function __construct(
        private readonly BrandKitRepository $kits,
        private readonly LlmService $llm,
    ) {
    }

    public function listKits(Principal $p, int $limit = 200): array
    {
        return $this->kits->visibleTo($p)->orderByDesc('created_at')->limit($limit)->get()->toArray();
    }

    public function getKit(Principal $p, int $id): BrandKit
    {
        $kit = $this->kits->find($p, $id);
        if (!$kit) {
            throw new ModelNotFoundException('Brand kit not found');
        }
        return $kit;
    }

    /** Brand allowance for a client (from the active subscription's plan). 0 = none/unknown. */
    public function allowance(Principal $p, ?string $clientEmail = null): array
    {
        $tenant = $this->resolveTenant($p, ['client_email' => $clientEmail]);
        $allowed = $this->allowedBrands($tenant);
        $used = BrandKit::where('client_email', $tenant)->count();
        return ['client_email' => $tenant, 'used' => $used, 'allowed' => $allowed];
    }

    public function createKit(Principal $p, array $data): BrandKit
    {
        $clientEmail = $this->resolveTenant($p, $data);

        $allowed = $this->allowedBrands($clientEmail);
        $used = BrandKit::where('client_email', $clientEmail)->count();
        if ($allowed > 0 && $used >= $allowed) {
            throw new AuthorizationException("Brand limit reached ({$used} of {$allowed}). Upgrade the subscription to add more brands.");
        }

        $payload = $this->pick($data, self::KIT_FIELDS);
        $payload['client_email'] = $clientEmail;
        if (empty($payload['brand_name'])) {
            throw new InvalidArgumentException('brand_name is required');
        }
        return BrandKit::create($payload);
    }

    public function updateKit(Principal $p, int $id, array $data): BrandKit
    {
        $kit = $this->getKit($p, $id);
        if (!$this->canWrite($p, $kit->client_email)) {
            throw new AuthorizationException('You cannot edit this brand kit');
        }
        $kit->fill($this->pick($data, self::KIT_FIELDS))->save();
        return $kit;
    }

    public function deleteKit(Principal $p, int $id): void
    {
        $kit = $this->getKit($p, $id);
        if (!$this->canWrite($p, $kit->client_email)) {
            throw new AuthorizationException('You cannot delete this brand kit');
        }
        BrandAsset::where('brand_kit_id', $kit->id)->delete();
        $kit->delete();
    }

    public function listAssets(Principal $p, int $kitId): array
    {
        $kit = $this->getKit($p, $kitId);
        $assets = BrandAsset::where('brand_kit_id', $kit->id)->orderByDesc('created_at')->get();
        $reviews = BrandAssetReview::whereIn('brand_asset_id', $assets->pluck('id'))->get()->keyBy('brand_asset_id');
        return $assets->map(fn ($a) => array_merge($a->toArray(), ['review' => $reviews->get($a->id)?->toArray()]))->all();
    }

    public function createAsset(Principal $p, int $kitId, array $data): BrandAsset
    {
        $kit = $this->getKit($p, $kitId);
        if (!$this->canWrite($p, $kit->client_email)) {
            throw new AuthorizationException('You cannot add assets to this brand kit');
        }
        $payload = $this->pick($data, self::ASSET_FIELDS);
        if (empty($payload['name'])) {
            throw new InvalidArgumentException('name is required');
        }
        $payload['brand_kit_id'] = $kit->id;
        $payload['client_email'] = $kit->client_email;
        return BrandAsset::create($payload);
    }

    public function deleteAsset(Principal $p, int $assetId): void
    {
        $asset = BrandAsset::find($assetId);
        if (!$asset) {
            throw new ModelNotFoundException('Asset not found');
        }
        $kit = $this->kits->find($p, (int) $asset->brand_kit_id);
        if (!$kit || !$this->canWrite($p, $kit->client_email)) {
            throw new AuthorizationException('You cannot delete this asset');
        }
        BrandAssetReview::where('brand_asset_id', $asset->id)->delete();
        $asset->delete();
    }

    /** AI brand-consistency review of an asset against its brand kit. */
    public function reviewAsset(Principal $p, int $assetId): BrandAssetReview
    {
        $asset = BrandAsset::find($assetId);
        if (!$asset) {
            throw new ModelNotFoundException('Asset not found');
        }
        $kit = $this->kits->find($p, (int) $asset->brand_kit_id);
        if (!$kit) {
            throw new ModelNotFoundException('Asset not found');
        }

        $r = $this->llm->reviewBrandAsset($asset->toArray(), $kit->toArray());

        return BrandAssetReview::updateOrCreate(
            ['brand_asset_id' => $asset->id],
            [
                'brand_kit_id' => $kit->id,
                'client_email' => $kit->client_email,
                'asset_name' => $asset->name,
                'file_url' => $asset->file_url,
                'status' => $r['status'],
                'summary' => $r['summary'],
                'score' => $r['score'],
                'matches' => $r['matches'],
                'issues' => $r['issues'],
                'recommendations' => $r['recommendations'],
            ],
        );
    }

    // ── helpers ──────────────────────────────────────────────
    private function allowedBrands(string $clientEmail): int
    {
        $sub = Subscription::where('client_email', $clientEmail)->where('status', 'active')->orderByDesc('created_at')->first();
        if (!$sub) {
            return 1; // default when no subscription on file
        }
        $plan = $sub->plan_id ? SubscriptionPlan::find($sub->plan_id) : null;
        return (int) ($plan->brand_kit_allowance ?? 1);
    }

    private function canWrite(Principal $p, string $clientEmail): bool
    {
        if ($p->isAgency()) {
            return true;
        }
        return $p->role === Role::ClientOwner && $p->clientEmail === $clientEmail;
    }

    private function resolveTenant(Principal $p, array $data): string
    {
        if ($p->isAgency()) {
            $email = $data['client_email'] ?? $p->impersonatedClientEmail ?? null;
            if (!$email) {
                throw new InvalidArgumentException('client_email is required');
            }
            return $email;
        }
        return (string) $p->clientEmail;
    }

    private function pick(array $data, array $fields): array
    {
        return array_intersect_key($data, array_flip($fields));
    }
}
