<?php

declare(strict_types=1);

namespace App\Services\Governance;

use App\Auth\Principal;
use App\Domain\Models\AccountDeletion;
use App\Domain\Models\Subscription;
use App\Policies\AuthorizationException;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Account/data deletion lifecycle (Master Spec §17.4): manager-initiated
 * soft-delete → grace period → hard purge (the destructive purge runs in a
 * job, disabled by default). This service only marks intent; it never deletes
 * data itself.
 */
final class AccountDeletionService
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly array $governance,
    ) {
    }

    /** Soft-delete: record the request + move the subscription to pending_deletion (reversible). */
    public function requestDeletion(Principal $manager, string $clientEmail): AccountDeletion
    {
        $this->assertManager($manager);
        $clientEmail = strtolower(trim($clientEmail));
        if ($clientEmail === '') {
            throw new InvalidArgumentException('client_email is required');
        }

        $grace = (int) ($this->governance['deletion_grace_days'] ?? 30);
        $deletion = AccountDeletion::create([
            'client_email'    => $clientEmail,
            'requested_by'    => $manager->email,
            'status'          => 'pending_purge',
            'grace_days'      => $grace,
            'soft_deleted_at' => date('Y-m-d H:i:s'),
            'purge_after'     => date('Y-m-d H:i:s', time() + $grace * 86400),
        ]);

        Subscription::where('client_email', $clientEmail)->update(['status' => 'pending_deletion']);
        $this->activity->log($manager, 'account_deletion_requested', null, null, 'pending_purge', 'account', ['client_email' => $clientEmail, 'purge_after' => $deletion->purge_after]);
        return $deletion;
    }

    /** Cancel within the grace window: restore the account. */
    public function cancel(Principal $manager, int $id): AccountDeletion
    {
        $this->assertManager($manager);
        $deletion = AccountDeletion::find($id);
        if (!$deletion) {
            throw new ModelNotFoundException('Deletion request not found');
        }
        if ($deletion->status !== 'pending_purge') {
            throw new InvalidArgumentException('Only a pending deletion can be cancelled');
        }
        $deletion->status = 'cancelled';
        $deletion->save();
        Subscription::where('client_email', $deletion->client_email)->update(['status' => 'active']);
        $this->activity->log($manager, 'account_deletion_cancelled', null, 'pending_purge', 'cancelled', 'account', ['client_email' => $deletion->client_email]);
        return $deletion;
    }

    public function list(Principal $manager): array
    {
        $this->assertManager($manager);
        return AccountDeletion::orderByDesc('created_at')->limit(100)->get()->toArray();
    }

    private function assertManager(Principal $p): void
    {
        if (!$p->isManager()) {
            throw new AuthorizationException('Manager access required');
        }
    }
}
