<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Auth\Principal;
use InvalidArgumentException;

/**
 * The shared signed-decision pattern (Master Spec §11). Captures + validates the
 * common bits of every human approval — the decision value, an optional comment,
 * a digital signature, and the acting principal — so request approvals and task
 * approvals record decisions identically.
 *
 * Deliberately field-flexible so it fits both shapes: it reads `signature` or the
 * legacy `digital_signature`, and `comment` / `note` / `approval_note`.
 *
 * NOTE: post (publishing) approvals do NOT use this — they require no signature
 * and write no activity/notification, so forcing them through here would distort
 * them (divergence flagged, not forced).
 */
final class SignedDecision
{
    public function __construct(
        public readonly string $decision,
        public readonly ?string $comment,
        public readonly string $signature,
        public readonly string $actorEmail,
        public readonly string $actorName,
    ) {
    }

    /**
     * @param string[] $allowed          permitted decision values
     * @param bool     $requireSignature enforce a non-empty signature
     * @param bool     $requireComment   enforce a non-empty comment
     */
    public static function capture(
        Principal $p,
        array $data,
        array $allowed,
        bool $requireSignature = true,
        bool $requireComment = false,
    ): self {
        $decision = (string) ($data['decision'] ?? '');
        if (!in_array($decision, $allowed, true)) {
            throw new InvalidArgumentException('decision must be one of: ' . implode(', ', $allowed));
        }

        $signature = trim((string) ($data['signature'] ?? $data['digital_signature'] ?? ''));
        if ($requireSignature && $signature === '') {
            throw new InvalidArgumentException('signature is required');
        }

        $rawComment = $data['comment'] ?? $data['note'] ?? $data['approval_note'] ?? null;
        $comment = $rawComment === null ? null : (string) $rawComment;
        if ($requireComment && trim((string) $comment) === '') {
            throw new InvalidArgumentException('comment is required');
        }

        return new self($decision, $comment, $signature, $p->email, $p->name ?: $p->email);
    }
}
