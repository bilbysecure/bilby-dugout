<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Project quotes (Master Spec §10 / §11 Layer A — the commercial gate). A quote
 * is drafted by a manager, sent, and accepted by the client owner with a digital
 * signature. On acceptance the terms are snapshotted; a Stripe deposit Checkout
 * then unlocks the project.
 */
final class CreateProjectQuotes extends AbstractMigration
{
    public function change(): void
    {
        $this->table('project_quotes')
            ->addColumn('project_id', 'biginteger', ['signed' => false])
            ->addColumn('client_email', 'string', ['limit' => 255])
            ->addColumn('line_items', 'text', ['null' => true])       // JSON [{desc, qty, unit_price}]
            ->addColumn('subtotal', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('deposit_pct', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0])
            ->addColumn('deposit_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('currency', 'string', ['limit' => 8, 'default' => 'AUD'])
            ->addColumn('terms', 'text', ['null' => true])
            ->addColumn('valid_until', 'date', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'draft']) // draft|sent|accepted|declined|expired
            ->addColumn('accepted_by', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('accepted_signature', 'text', ['null' => true])
            ->addColumn('accepted_at', 'datetime', ['null' => true])
            ->addColumn('terms_snapshot', 'text', ['null' => true])   // JSON frozen at acceptance
            ->addColumn('stripe_deposit_intent_id', 'string', ['limit' => 191, 'null' => true])
            ->addTimestamps()
            ->addIndex('project_id')
            ->addIndex('client_email')
            ->addIndex('status')
            ->addForeignKey('project_id', 'projects', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
