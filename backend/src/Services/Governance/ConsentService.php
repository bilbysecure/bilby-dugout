<?php

declare(strict_types=1);

namespace App\Services\Governance;

use App\Domain\Models\ConsentRecord;

/**
 * Captures accepted privacy-policy/terms versions per user (Master Spec §17.4).
 * Called at signup (invite acceptance) and at quote acceptance.
 */
final class ConsentService
{
    /** @param array $governance settings['governance'] */
    public function __construct(private readonly array $governance)
    {
    }

    /**
     * Record consent for the current privacy + terms versions.
     *
     * @return ConsentRecord[]
     */
    public function record(string $userEmail, ?string $clientEmail, string $context, ?string $ip = null): array
    {
        $versions = [
            'privacy' => (string) ($this->governance['privacy_version'] ?? ''),
            'terms'   => (string) ($this->governance['terms_version'] ?? ''),
        ];

        $rows = [];
        foreach ($versions as $type => $version) {
            $rows[] = ConsentRecord::create([
                'user_email'   => $userEmail,
                'client_email' => $clientEmail,
                'policy_type'  => $type,
                'version'      => $version,
                'context'      => $context,
                'accepted_at'  => date('Y-m-d H:i:s'),
                'ip_address'   => $ip,
            ]);
        }
        return $rows;
    }
}
