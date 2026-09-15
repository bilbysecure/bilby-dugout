<?php

declare(strict_types=1);

namespace App\Console;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\RssSource;
use App\Services\Publishing\RssService;

/** Pulls all active RSS sources into scheduled posts. */
final class RssIngestCommand
{
    public function __construct(private readonly RssService $rss)
    {
    }

    public function handle(): int
    {
        $system = new Principal(0, 'system@bilbypixel.com', 'System', Role::GlobalAdmin);
        $sources = RssSource::where('status', 'active')->get();

        $created = 0;
        $skipped = 0;
        foreach ($sources as $source) {
            $result = $this->rss->ingestSource($system, $source);
            $created += $result['created'];
            $skipped += $result['skipped'];
        }

        fwrite(STDOUT, "rss:ingest — {$created} new post(s), {$skipped} skipped, across {$sources->count()} source(s).\n");
        return 0;
    }
}
