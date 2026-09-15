<?php

declare(strict_types=1);

namespace App\Services\Teams;

/** Boundary for relaying comments to Microsoft Teams. Real Graph client swaps in later. */
interface TeamsNotifier
{
    public function relayComment(string $requestTitle, string $author, string $message, bool $internal): void;
}
