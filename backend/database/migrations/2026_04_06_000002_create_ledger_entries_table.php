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

            $table->unique(['transaction_id', 'entry_type'], 'ledger_idempotency_unique');
            $table->index(['account_id', 'entry_type'], 'ledger_account_type_index');
            $table->index(['account_id', 'created_at'], 'ledger_account_created_at_index');

            $table->foreign('account_id')
                ->references('account_id')
                ->on('accounts')
                ->onDelete('restrict');

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
