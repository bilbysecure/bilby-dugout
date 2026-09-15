<?php

declare(strict_types=1);

namespace App\Services\Teams;

use App\Domain\Models\EmailOutbox;

/** Records what would be posted to Teams into the outbox (no creds needed). */
final class OutboxTeamsNotifier implements TeamsNotifier
{
    public function relayComment(string $requestTitle, string $author, string $message, bool $internal): void
    {
        EmailOutbox::create([
            'channel'    => 'teams',
            'recipient'  => 'Teams channel',
            'subject'    => ($internal ? 'Internal note' : 'New comment') . ' on ' . $requestTitle,
            'body'       => $author . ': ' . $message,
            'type'       => 'comment',
            'status'     => 'simulated',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
