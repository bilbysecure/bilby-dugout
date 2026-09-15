<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Models\ActivityLog;
use App\Support\RequestGuard;

/** Read access to the audit trail of a request (scoped via request visibility). */
final class ActivityService
{
    public function __construct(private readonly RequestGuard $guard)
    {
    }

    public function listForRequest(Principal $p, int $requestId, int $limit = 200): array
    {
        $request = $this->guard->visibleOrFail($p, $requestId);
        return ActivityLog::where('request_id', $request->id)
            ->orderByDesc('created_at')->limit($limit)->get()->toArray();
    }
}
