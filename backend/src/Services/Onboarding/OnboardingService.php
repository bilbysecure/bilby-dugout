<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Auth\AuthService;
use App\Auth\Principal;
use App\Domain\Models\ClientOnboardingPreference;
use App\Domain\Models\User;
use App\Policies\AuthorizationException;
use App\Services\ActivityLogger;
use App\Services\Governance\ConsentService;
use App\Services\Mail\Mailer;
use App\Support\SignedLink;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Invite-only onboarding (Master Spec §17.3, v1 path). A manager invites a
 * client + owner; provisioning is delegated to TenantProvisioningService (the
 * SAME service a future public self-signup will use — see that class's extension
 * point). The owner receives a signed, expiring set-password link via email.
 */
final class OnboardingService
{
    private const PURPOSE = 'set_password';
    private const PREF_FIELDS = ['preferred_contact_method', 'turnaround_expectations', 'approval_contact_name', 'approval_contact_email', 'notes'];

    public function __construct(
        private readonly TenantProvisioningService $provisioning,
        private readonly Mailer $mailer,
        private readonly AuthService $auth,
        private readonly ConsentService $consent,
        private readonly ActivityLogger $activity,
        private readonly string $secret,
        private readonly array $config, // settings['onboarding']
    ) {
    }

    /** Manager creates a client + owner and sends the set-password invite. */
    public function invite(Principal $manager, array $data): array
    {
        $this->assertManager($manager);

        $result = $this->provisioning->provision([
            'owner_email'  => $data['owner_email'] ?? '',
            'company_name' => $data['company_name'] ?? '',
            'owner_name'   => $data['owner_name'] ?? '',
            'plan_id'      => $data['plan_id'] ?? null,
            'plan_name'    => $data['plan_name'] ?? null,
        ]);
        $email = $result['owner']->email;

        $token = SignedLink::sign(['email' => $email, 'purpose' => self::PURPOSE], (int) ($this->config['invite_ttl'] ?? 259200), $this->secret);
        $link = rtrim((string) $this->config['frontend_url'], '/') . '/set-password?token=' . rawurlencode($token);

        $this->mailer->send(
            $email,
            'Set up your BilbyDugout account',
            "You've been invited to BilbyDugout. Set your password to get started:\n\n{$link}\n\nThis link expires soon.",
            'invite',
        );
        $this->activity->log($manager, 'client_invited', null, null, null, 'client', ['client_email' => $email]);

        // The link is returned for local/dev convenience (email goes to the outbox).
        return ['client_email' => $email, 'invite_link' => $link];
    }

    /** Owner opens the link, sets a password, verifies email → gets a session. */
    public function acceptInvite(string $token, string $password, ?string $ip = null): array
    {
        $payload = SignedLink::verify($token, $this->secret); // throws on tamper/expiry
        if (($payload['purpose'] ?? null) !== self::PURPOSE) {
            throw new InvalidArgumentException('Invalid invite token');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }

        $email = strtolower((string) $payload['email']);
        $user = User::where('email', $email)->first();
        if (!$user) {
            throw new ModelNotFoundException('Invite is no longer valid');
        }

        $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $user->email_verified_at = date('Y-m-d H:i:s');
        $user->save();

        // Consent captured at signup (privacy + terms).
        $this->consent->record($email, $email, 'signup', $ip);

        return $this->auth->sessionFor($email);
    }

    public function getPreferences(Principal $p): array
    {
        return (ClientOnboardingPreference::firstOrNew(['client_email' => (string) $p->clientEmail]))->toArray();
    }

    public function savePreferences(Principal $p, array $data): ClientOnboardingPreference
    {
        if (!$p->isClient()) {
            throw new AuthorizationException('Client access required');
        }
        $prefs = ClientOnboardingPreference::firstOrNew(['client_email' => (string) $p->clientEmail]);
        $prefs->fill(array_intersect_key($data, array_flip(self::PREF_FIELDS)));
        $prefs->save();
        return $prefs;
    }

    public function complete(Principal $p): void
    {
        User::where('email', $p->email)->update(['onboarding_completed' => true]);
    }

    private function assertManager(Principal $p): void
    {
        if (!$p->isManager()) {
            throw new AuthorizationException('Manager access required');
        }
    }
}
