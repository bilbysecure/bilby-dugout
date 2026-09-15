<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Models\TimeEntry;
use App\Support\RequestGuard;
use InvalidArgumentException;

/** Time tracking. Only agency staff working the request may log time. */
final class TimeService
{
    public function __construct(private readonly RequestGuard $guard)
    {
    }

    public function list(Principal $p, int $requestId): array
    {
        $request = $this->guard->visibleOrFail($p, $requestId);
        // Clients don't see internal effort logs.
        if ($p->isClient()) {
            return [];
        }
        return TimeEntry::where('request_id', $request->id)->orderByDesc('created_at')->get()->toArray();
    }

    public function create(Principal $p, int $requestId, array $data): TimeEntry
    {
        $request = $this->guard->visibleOrFail($p, $requestId);
        $this->guard->assertAgencyWorkerOn($p, $request, 'Only assigned staff can log time');

        $seconds = (int) ($data['seconds'] ?? 0);
        if ($seconds <= 0) {
            throw new InvalidArgumentException('seconds must be a positive integer');
        }

        return TimeEntry::create([
            'request_id' => $request->id,
            'user_email' => $p->email,
            'seconds'    => $seconds,
            'note'       => $data['note'] ?? null,
            'started_at' => $data['started_at'] ?? null,
            'ended_at'   => $data['ended_at'] ?? null,
        ]);
    }
}
