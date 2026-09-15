<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Auth\Principal;
use App\Domain\Models\RssSource;
use App\Domain\Models\ScheduledPost;
use App\Domain\Models\SocialChannel;
use App\Support\TenantScope;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Automated RSS publishing: manage feed sources and ingest their items into
 * scheduled posts (draft or pending-approval), with tag automation and optional
 * best-time auto-scheduling. Ingestion uses public feeds — no external creds.
 */
final class RssService
{
    private const FIELDS = ['brand_kit_id', 'name', 'feed_url', 'default_channel_ids', 'auto_schedule', 'default_status', 'apply_tag_rules', 'status'];

    private Client $http;

    public function __construct(
        private readonly PublishingService $publishing,
        private readonly BestTimeService $bestTime,
    ) {
        $this->http = new Client(['timeout' => 20]);
    }

    public function list(Principal $p): array
    {
        return TenantScope::apply(RssSource::query(), $p)->orderBy('name')->get()->toArray();
    }

    public function create(Principal $p, array $data): RssSource
    {
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (empty($payload['name']) || empty($payload['feed_url'])) {
            throw new InvalidArgumentException('name and feed_url are required');
        }
        $payload['client_email'] = TenantScope::tenantFor($p, $data['client_email'] ?? null);
        return RssSource::create($payload);
    }

    public function update(Principal $p, int $id, array $data): RssSource
    {
        $source = $this->findScoped($p, $id);
        $source->fill(array_intersect_key($data, array_flip(self::FIELDS)))->save();
        return $source;
    }

    public function delete(Principal $p, int $id): void
    {
        $this->findScoped($p, $id)->delete();
    }

    /**
     * Fetch a source's feed and create posts for new items.
     * @return array{created:int,skipped:int}
     */
    public function ingest(Principal $p, int $id): array
    {
        $source = $this->findScoped($p, $id);
        return $this->ingestSource($p, $source);
    }

    /** @return array{created:int,skipped:int} */
    public function ingestSource(Principal $p, RssSource $source): array
    {
        $created = 0;
        $skipped = 0;

        try {
            $body = (string) $this->http->get($source->feed_url)->getBody();
            $items = $this->parse($body);
        } catch (\Throwable $e) {
            $source->last_fetched_at = now();
            $source->last_status = 'error: ' . mb_substr($e->getMessage(), 0, 180);
            $source->save();
            return ['created' => 0, 'skipped' => 0];
        }

        $channelIds = array_map('intval', (array) ($source->default_channel_ids ?? []));

        foreach ($items as $item) {
            $guid = $item['guid'] ?: $item['link'] ?: md5($item['title']);

            $exists = ScheduledPost::where('rss_source_id', $source->id)->where('rss_guid', $guid)->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            $data = [
                'client_email' => $source->client_email,
                'brand_kit_id' => $source->brand_kit_id,
                'title'        => $item['title'],
                'caption'      => trim(($item['title'] ? $item['title'] . "\n\n" : '') . $item['summary']),
                'link'         => $item['link'],
                'channel_ids'  => $channelIds,
                'status'       => $source->default_status,
            ];

            if ($source->auto_schedule && $channelIds) {
                $platform = optional(SocialChannel::find($channelIds[0]))->platform ?? 'instagram';
                $slot = $this->bestTime->suggest($platform, 'UTC', 1)[0]['datetime'] ?? null;
                if ($slot) {
                    $data['scheduled_at'] = str_replace('T', ' ', $slot) . ':00';
                    $data['status'] = $source->default_status === 'draft' ? 'scheduled' : $source->default_status;
                }
            }

            $post = $this->publishing->create($p, $data);
            $post->rss_source_id = $source->id;
            $post->rss_guid = $guid;
            $post->save();
            $created++;
        }

        $source->last_fetched_at = now();
        $source->last_status = "ok: {$created} new, {$skipped} skipped";
        $source->save();

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** @return array<int,array{title:string,link:string,summary:string,guid:string}> */
    private function parse(string $xml): array
    {
        $sx = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($sx === false) {
            return [];
        }
        $out = [];

        // RSS 2.0
        if (isset($sx->channel->item)) {
            foreach ($sx->channel->item as $item) {
                $out[] = [
                    'title'   => trim((string) $item->title),
                    'link'    => trim((string) $item->link),
                    'summary' => trim(strip_tags((string) $item->description)),
                    'guid'    => trim((string) ($item->guid ?? '')),
                ];
            }
            return $out;
        }

        // Atom
        if (isset($sx->entry)) {
            foreach ($sx->entry as $entry) {
                $link = '';
                foreach ($entry->link as $l) {
                    if ((string) $l['rel'] === '' || (string) $l['rel'] === 'alternate') {
                        $link = (string) $l['href'];
                        break;
                    }
                }
                $out[] = [
                    'title'   => trim((string) $entry->title),
                    'link'    => $link,
                    'summary' => trim(strip_tags((string) ($entry->summary ?: $entry->content))),
                    'guid'    => trim((string) $entry->id),
                ];
            }
        }
        return $out;
    }

    private function findScoped(Principal $p, int $id): RssSource
    {
        $source = TenantScope::apply(RssSource::query(), $p)->whereKey($id)->first();
        if (!$source) {
            throw new ModelNotFoundException('RSS source not found');
        }
        return $source;
    }
}
