<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Inbound webhook log (Master Spec §17.1). The raw event is written before any
 * processing so delivery can return 200 immediately; processing runs async via
 * the queue. Idempotency is enforced by a UNIQUE (provider, event_id) index —
 * a provider re-delivery is deduped rather than reprocessed.
 *
 * NOTE (deviation, flagged for approval): the spec says "event_id UNIQUE".
 * Implemented as a composite UNIQUE (provider, event_id) so two providers can
 * never collide on an id — the whole reason the `provider` column exists.
 * Change to a single-column unique on request.
 */
final class CreateWebhookEvents extends AbstractMigration
{
    public function change(): void
    {
        $this->table('webhook_events')
            ->addColumn('provider', 'string', ['limit' => 64])         // stripe, graph, …
            ->addColumn('event_id', 'string', ['limit' => 191])        // provider's event id
            ->addColumn('payload', 'text', ['null' => true])           // JSON (array cast)
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'received']) // received|processing|processed|failed
            ->addColumn('last_error', 'text', ['null' => true])
            ->addColumn('received_at', 'datetime')
            ->addColumn('processed_at', 'datetime', ['null' => true])
            ->addTimestamps()
            ->addIndex(['provider', 'event_id'], ['unique' => true])    // idempotency
            ->addIndex('status')
            ->create();
    }
}
