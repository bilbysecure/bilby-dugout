<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Models\QuotaNotification;
use App\Domain\Models\Request;
use App\Domain\Models\Subscription;
use App\Services\Mail\Mailer;

/** Emails clients at 80% / 100% of their monthly request quota (idempotent per period). */
final class QuotaCheckCommand
{
    private const ACTIVE = ['submitted', 'in_progress', 'review', 'revision', 'approved', 'scheduled', 'published', 'completed'];

    public function __construct(private readonly Mailer $mailer)
    {
    }

    public function handle(): int
    {
        $now = new \DateTimeImmutable('now');
        $periodKey = $now->format('Y-m');
        $start = $now->format('Y-m-01 00:00:00');
        $end = $now->modify('first day of next month')->format('Y-m-01 00:00:00');

        $subs = Subscription::where('status', 'active')->get()
            ->filter(fn ($s) => (int) $s->monthly_request_limit > 0);

        $checked = 0;
        $sent = 0;
        foreach ($subs as $sub) {
            $checked++;
            $limit = (int) $sub->monthly_request_limit;
            $count = Request::where('client_email', $sub->client_email)
                ->whereIn('status', self::ACTIVE)
                ->where('created_at', '>=', $start)->where('created_at', '<', $end)
                ->count();
            $pct = ($count / $limit) * 100;

            foreach ([80, 100] as $threshold) {
                if ($pct < $threshold) {
                    continue;
                }
                $already = QuotaNotification::where('subscription_id', $sub->id)
                    ->where('threshold', $threshold)->where('period_key', $periodKey)->exists();
                if ($already) {
                    continue;
                }
                $this->mailer->send(
                    $sub->client_email,
                    "You've reached {$threshold}% of your monthly request quota",
                    "Hi {$sub->client_name},\n\nYou've used {$count} of {$limit} requests on your {$sub->plan_name} plan this month.\n\n— The BilbyPixel team",
                    'quota',
                );
                QuotaNotification::create([
                    'client_email' => $sub->client_email,
                    'subscription_id' => $sub->id,
                    'threshold' => $threshold,
                    'period_key' => $periodKey,
                    'request_count' => $count,
                ]);
                $sent++;
            }
        }

        fwrite(STDOUT, "quota:check — checked {$checked} subscription(s), sent {$sent} email(s).\n");
        return 0;
    }
}
