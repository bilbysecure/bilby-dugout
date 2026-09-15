<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Models\Request;
use App\Domain\Models\Subscription;
use App\Services\Mail\Mailer;
use App\Services\NotificationService;

/** Reminds client owners about requests stuck in pending_owner_approval > 48h. */
final class ApprovalsRemindCommand
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly NotificationService $notifications,
    ) {
    }

    public function handle(): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - 48 * 3600);
        $stale = Request::where('status', 'pending_owner_approval')
            ->where('created_at', '<', $cutoff)->get();

        $sent = 0;
        foreach ($stale as $r) {
            $sub = Subscription::where('client_email', $r->client_email)
                ->where('status', 'active')->where('require_owner_approval', true)->first();
            if (!$sub) {
                continue;
            }
            $this->mailer->send(
                $r->client_email,
                'Requests awaiting your approval',
                "\"{$r->title}\" has been waiting more than 48 hours for owner approval. Please review it in your portal.",
                'approval_reminder',
            );
            $this->notifications->notify(
                $r->client_email,
                'status_change',
                'Approval reminder',
                "\"{$r->title}\" is awaiting your approval.",
                (int) $r->id,
            );
            $sent++;
        }

        fwrite(STDOUT, "approvals:remind — sent {$sent} reminder(s).\n");
        return 0;
    }
}
