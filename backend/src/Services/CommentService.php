<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Models\Comment;
use App\Services\Teams\TeamsNotifier;
use App\Support\RequestGuard;
use InvalidArgumentException;

final class CommentService
{
    public function __construct(
        private readonly RequestGuard $guard,
        private readonly NotificationService $notifications,
        private readonly ActivityLogger $activity,
        private readonly TeamsNotifier $teams,
    ) {
    }

    /** Clients never see internal notes. */
    public function list(Principal $p, int $requestId): array
    {
        $request = $this->guard->visibleOrFail($p, $requestId);

        $query = Comment::where('request_id', $request->id)->orderBy('created_at');
        if ($p->isClient()) {
            $query->where('is_internal', false);
        }
        return $query->get()->toArray();
    }

    public function create(Principal $p, int $requestId, array $data): Comment
    {
        $request = $this->guard->visibleOrFail($p, $requestId);
        $this->guard->assertParticipant($p, $request, 'You cannot comment on this request');

        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '') {
            throw new InvalidArgumentException('message is required');
        }

        // Only agency users may post internal notes.
        $isInternal = $p->isAgency() && (bool) ($data['is_internal'] ?? false);

        $comment = Comment::create([
            'request_id'  => $request->id,
            'author_email' => $p->email,
            'author_name'  => $p->name ?: $p->email,
            'message'      => $message,
            'is_internal'  => $isInternal,
            'attachments'  => is_array($data['attachments'] ?? null) ? $data['attachments'] : [],
        ]);

        // Notify the other side. Internal notes only fan out to assigned staff.
        $recipients = $isInternal
            ? $request->assigned_to
            : array_merge($request->assigned_to, [$request->client_email]);
        $recipients = array_values(array_diff($recipients, [$p->email]));

        $this->notifications->notifyMany(
            $recipients,
            'comment_mention',
            'New comment on ' . ($request->title ?: "request #{$request->id}"),
            mb_substr($message, 0, 140),
            $request->id,
            $comment->id,
        );

        $this->activity->log($p, 'comment_added', $request, metadata: ['internal' => $isInternal]);

        // Relay to Microsoft Teams (mock records to the outbox; real Graph client swaps in later).
        $this->teams->relayComment($request->title ?: "request #{$request->id}", $p->name ?: $p->email, $message, $isInternal);

        return $comment;
    }
}
