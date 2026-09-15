<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Models\RequestReview;
use App\Policies\AuthorizationException;
use App\Support\RequestGuard;
use InvalidArgumentException;

/** Client satisfaction rating + review for a completed request. */
final class ReviewService
{
    public function __construct(
        private readonly RequestGuard $guard,
        private readonly ActivityLogger $activity,
    ) {
    }

    public function list(Principal $p, int $requestId): array
    {
        $request = $this->guard->visibleOrFail($p, $requestId);
        return RequestReview::where('request_id', $request->id)->orderByDesc('created_at')->get()->toArray();
    }

    public function create(Principal $p, int $requestId, array $data): RequestReview
    {
        $request = $this->guard->visibleOrFail($p, $requestId);

        if (!$this->guard->isTenantClientOf($p, $request)) {
            throw new AuthorizationException('Only the client can review this request');
        }

        $rating = (int) ($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('rating must be between 1 and 5');
        }

        $review = RequestReview::create([
            'request_id'         => $request->id,
            'request_title'      => $request->title,
            'client_email'       => $request->client_email,
            'rating'             => $rating,
            'review'             => $data['review'] ?? null,
            'submitted_by_name'  => $p->name ?: $p->email,
            'submitted_by_email' => $p->email,
        ]);

        $this->activity->log($p, 'review_submitted', $request, metadata: ['rating' => $rating]);
        return $review;
    }
}
