<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\AccountDeletion;
use App\Domain\Models\BrandKit;
use App\Domain\Models\ClientMember;
use App\Domain\Models\ConsentRecord;
use App\Domain\Models\DataExport;
use App\Domain\Models\Invoice;
use App\Domain\Models\Job;
use App\Domain\Models\Media;
use App\Domain\Models\Project;
use App\Domain\Models\Request;
use App\Domain\Models\ScheduledPost;
use App\Domain\Models\Subscription;
use App\Domain\Models\Task;
use App\Services\Jobs\JobHandler;
use App\Services\Media\MediaStorage;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Hard purge of soft-deleted accounts past their grace window (Master Spec §17.4):
 * removes tenant DB rows AND blobs.
 *
 * ⚠️ DESTRUCTIVE. Guarded by governance.purge_enabled (default FALSE), so it is a
 * no-op until an operator explicitly enables it. Nothing here runs against
 * existing data without that opt-in.
 */
final class AccountPurgeHandler implements JobHandler
{
    public function __construct(
        private readonly MediaStorage $storage,
        private readonly array $governance,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Job $job): void
    {
        if (!($this->governance['purge_enabled'] ?? false)) {
            $this->logger->info('account.purge skipped — purge_enabled is false');
            return;
        }

        $due = AccountDeletion::where('status', 'pending_purge')
            ->where('purge_after', '<=', date('Y-m-d H:i:s'))->get();

        foreach ($due as $deletion) {
            $client = (string) $deletion->client_email;

            // Blobs first.
            foreach (Media::where('client_email', $client)->get() as $m) {
                try {
                    $this->storage->delete($m->path);
                } catch (Throwable) {
                    // best-effort; continue purging DB rows
                }
            }

            // Tenant DB rows (children cascade via FKs where defined).
            Request::where('client_email', $client)->delete();
            Task::where('client_email', $client)->delete();
            Project::where('client_email', $client)->delete();
            ScheduledPost::where('client_email', $client)->delete();
            BrandKit::where('client_email', $client)->delete();
            Invoice::where('client_email', $client)->delete();
            Subscription::where('client_email', $client)->delete();
            ClientMember::where('client_email', $client)->delete();
            ConsentRecord::where('client_email', $client)->delete();
            Media::where('client_email', $client)->delete();
            DataExport::where('client_email', $client)->delete();

            $deletion->status = 'purged';
            $deletion->purged_at = date('Y-m-d H:i:s');
            $deletion->save();
            $this->logger->warning("account purged: {$client}");
        }
    }
}
