<?php

declare(strict_types=1);

namespace App\Services\Mail;

/** Boundary for outbound email. A real Microsoft Graph mailer swaps in for OutboxMailer in prod. */
interface Mailer
{
    public function send(string $to, string $subject, string $body, ?string $type = null): void;
}
