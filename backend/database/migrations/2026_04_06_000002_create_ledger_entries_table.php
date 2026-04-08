<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 100);
            $table->string('account_id', 10);
            $table->enum('entry_type', ['credit', 'debit']);
            $table->decimal('amount', 15, 2)->unsigned();
            $table->string('counterparty_account_id', 10);
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // ── Indexes ──────────────────────────────────────────────
            // Compound unique index enforces idempotency:
            // Each transaction_id creates exactly 2 entries (1 debit + 1 credit)
            $table->unique(['transaction_id', 'account_id'], 'ledger_idempotency_unique');

            // Fast lookups for balance calculation and transaction history
            $table->index('account_id');
            $table->index('transaction_id');
            $table->index(['account_id', 'created_at']);

            // ── Foreign Keys ─────────────────────────────────────────
            $table->foreign('account_id')
                  ->references('account_id')
                  ->on('accounts')
                  ->onDelete('restrict'); // Never delete an account with ledger entries

            $table->foreign('counterparty_account_id')
                  ->references('account_id')
                  ->on('accounts')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
