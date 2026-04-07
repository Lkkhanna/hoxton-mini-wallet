<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test accounts with initial balances
        Account::create(['account_id' => 'SENDER', 'name' => 'Sender']);
        Account::create(['account_id' => 'RECEIVER', 'name' => 'Receiver']);

        // Give sender an initial balance of 1000
        LedgerEntry::create([
            'transaction_id'          => 'SEED-SENDER',
            'account_id'              => 'SENDER',
            'entry_type'              => 'credit',
            'amount'                  => 1000.00,
            'counterparty_account_id' => 'SENDER',
            'description'             => 'Initial deposit',
        ]);
    }

    /** @test */
    public function it_can_transfer_money_between_accounts()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-001',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 250.00,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.status', 'completed')
                 ->assertJsonPath('data.amount', '250.00');

        // Verify sender balance decreased
        $senderBalance = $this->getJson('/api/accounts/SENDER/balance');
        $senderBalance->assertJsonPath('data.balance', '750.00');

        // Verify receiver balance increased
        $receiverBalance = $this->getJson('/api/accounts/RECEIVER/balance');
        $receiverBalance->assertJsonPath('data.balance', '250.00');
    }

    /** @test */
    public function it_creates_debit_and_credit_ledger_entries()
    {
        $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-LEDGER',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 100.00,
        ]);

        // Two entries for the transfer + 1 seed entry
        $this->assertDatabaseCount('ledger_entries', 3);

        // Debit entry for sender
        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => 'TXN-LEDGER',
            'account_id'     => 'SENDER',
            'entry_type'     => 'debit',
            'amount'         => 100.00,
        ]);

        // Credit entry for receiver
        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => 'TXN-LEDGER',
            'account_id'     => 'RECEIVER',
            'entry_type'     => 'credit',
            'amount'         => 100.00,
        ]);
    }

    /** @test */
    public function it_prevents_duplicate_transactions_idempotency()
    {
        // First transfer should succeed
        $response1 = $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-IDEM',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 100.00,
        ]);
        $response1->assertStatus(201);

        // Same transaction_id with the same payload should be replayed safely
        $response2 = $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-IDEM',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 100.00,
        ]);
        $response2->assertStatus(200)
            ->assertJsonPath('data.idempotency_status', 'replayed')
            ->assertJsonPath('meta.idempotency.replayed', true);

        // Balance should reflect only ONE transfer
        $senderBalance = $this->getJson('/api/accounts/SENDER/balance');
        $senderBalance->assertJsonPath('data.balance', '900.00');
    }

    /** @test */
    public function it_rejects_duplicate_transaction_id_when_payload_differs()
    {
        $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-CONFLICT',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 100.00,
        ])->assertStatus(201);

        $response = $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-CONFLICT',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 125.00,
        ]);

        $response->assertStatus(409)
            ->assertJsonValidationErrors('transaction_id');
    }

    /** @test */
    public function it_prevents_transfer_exceeding_balance()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-OVER',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 9999.00,
        ]);

        $response->assertStatus(422);

        // Balance should remain unchanged
        $senderBalance = $this->getJson('/api/accounts/SENDER/balance');
        $senderBalance->assertJsonPath('data.balance', '1000.00');
    }

    /** @test */
    public function it_rejects_negative_amount()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-NEG',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => -100.00,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('amount');
    }

    /** @test */
    public function it_rejects_zero_amount()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-ZERO',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 0,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('amount');
    }

    /** @test */
    public function it_rejects_self_transfer()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-SELF',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'SENDER',
            'amount'          => 100.00,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('to_account_id');
    }

    /** @test */
    public function it_rejects_transfer_from_nonexistent_account()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-GHOST',
            'from_account_id' => 'GHOST999',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 100.00,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('from_account_id');
    }

    /** @test */
    public function it_rejects_transfer_without_transaction_id()
    {
        $response = $this->postJson('/api/transfers', [
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 100.00,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('transaction_id');
    }

    /** @test */
    public function it_handles_exact_balance_transfer()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-EXACT',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 1000.00,
        ]);

        $response->assertStatus(201);

        $senderBalance = $this->getJson('/api/accounts/SENDER/balance');
        $senderBalance->assertJsonPath('data.balance', '0.00');
    }

    /** @test */
    public function it_handles_multiple_sequential_transfers()
    {
        // Transfer 1
        $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-SEQ1',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 300.00,
        ])->assertStatus(201);

        // Transfer 2
        $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-SEQ2',
            'from_account_id' => 'SENDER',
            'to_account_id'   => 'RECEIVER',
            'amount'          => 200.00,
        ])->assertStatus(201);

        // Transfer back
        $this->postJson('/api/transfers', [
            'transaction_id'  => 'TXN-SEQ3',
            'from_account_id' => 'RECEIVER',
            'to_account_id'   => 'SENDER',
            'amount'          => 100.00,
        ])->assertStatus(201);

        // Verify final balances
        $senderBalance = $this->getJson('/api/accounts/SENDER/balance');
        $senderBalance->assertJsonPath('data.balance', '600.00');

        $receiverBalance = $this->getJson('/api/accounts/RECEIVER/balance');
        $receiverBalance->assertJsonPath('data.balance', '400.00');
    }
}
