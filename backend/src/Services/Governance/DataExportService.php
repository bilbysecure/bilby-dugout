<?php

declare(strict_types=1);

namespace App\Services\Governance;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\DataExport;
use App\Policies\AuthorizationException;
use App\Services\Jobs\QueueService;

/**
 * Tenant data export (Master Spec §17.4). A client owner requests an export; a
 * background job (Phase 1) compiles the tenant's data into an archive stored as
 * a media record (Phase 2) which the owner can then download via a signed URL.
 */
final class DataExportService
{
    public function __construct(private readonly QueueService $queue)
    {
    }

    public function request(Principal $p): DataExport
    {
        if ($p->role !== Role::ClientOwner) {
            throw new AuthorizationException('Only the client owner can export account data');
        }
        $export = DataExport::create([
            'client_email' => $p->clientEmail,
            'requested_by' => $p->email,
            'status'       => 'pending',
        ]);
        $this->queue->enqueue('data.export', ['data_export_id' => $export->id], 0, (string) $p->clientEmail, 'governance');
        return $export;
    }

    public function list(Principal $p): array
    {
        $q = DataExport::query()->orderByDesc('created_at');
        if (!$p->isAgency()) {
            $q->where('client_email', $p->clientEmail);
        }
        return $q->limit(50)->get()->toArray();
    }
}
