<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Domain\Models\EmailOutbox;

/** Records "sent" mail to the outbox table instead of hitting a provider (no creds needed). */
final class OutboxMailer implements Mailer
{
    public function send(string $to, string $subject, string $body, ?string $type = null): void
    {
        EmailOutbox::create([
            'channel'    => 'email',
            'recipient'  => $to,
            'subject'    => $subject,
            'body'       => $body,
            'type'       => $type,
            'status'     => 'simulated',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
