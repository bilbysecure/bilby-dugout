<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Models\Notification;
use App\Policies\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * In-app notifications. Reads are strictly scoped to the recipient
 * (recipient_email == principal.email) — the only scope this entity uses.
 */
final class NotificationService
{
    /** Create a notification (used internally by other services). */
    public function notify(
        string $recipientEmail,
        string $type,
        string $title,
        string $message,
        ?int $requestId = null,
        ?int $commentId = null,
    ): void {
        if ($recipientEmail === '') {
            return;
        }
        Notification::create([
            'recipient_email' => $recipientEmail,
            'type'            => $type,
            'title'           => $title,
            'message'         => $message,
            'request_id'      => $requestId,
            'comment_id'      => $commentId,
        ]);
    }

    /** @param string[] $emails */
    public function notifyMany(array $emails, string $type, string $title, string $message, ?int $requestId = null, ?int $commentId = null): void
    {
        foreach (array_unique(array_filter($emails)) as $email) {
            $this->notify($email, $type, $title, $message, $requestId, $commentId);
        }
    }

    public function listFor(Principal $p, int $limit = 50): array
    {
        return Notification::where('recipient_email', $p->email)
            ->orderByDesc('created_at')->limit($limit)->get()->toArray();
    }

    public function markRead(Principal $p, int $id): Notification
    {
        $n = Notification::find($id);
        if (!$n) {
            throw new ModelNotFoundException('Notification not found');
        }
        if ($n->recipient_email !== $p->email) {
            throw new AuthorizationException('Not your notification');
        }
        $n->is_read = true;
        $n->save();
        return $n;
    }

    public function markAllRead(Principal $p): int
    {
        return Notification::where('recipient_email', $p->email)
            ->where('is_read', false)->update(['is_read' => true]);
    }
}
