<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Account::factory()->withAccountId('SENDER')->create(['name' => 'Sender']);
        Account::factory()->withAccountId('RECEIVER')->create(['name' => 'Receiver']);

        LedgerEntry::factory()->credit()->create([
            'transaction_id' => 'SEED-SENDER',
            'account_id' => 'SENDER',
            'amount' => 1000.00,
            'counterparty_account_id' => 'SENDER',
            'description' => 'Initial deposit',
        ]);
    }

    #[Test]
    public function it_can_transfer_money_between_accounts()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-001',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 250.00,
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.amount', '250.00');

        $this->getJson('/api/accounts/SENDER/balance')
            ->assertJsonPath('data.balance', '750.00');

        $this->getJson('/api/accounts/RECEIVER/balance')
            ->assertJsonPath('data.balance', '250.00');
    }

    #[Test]
    public function it_creates_debit_and_credit_ledger_entries()
    {
        $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-LEDGER',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 100.00,
        ]);

        $this->assertSame(2, LedgerEntry::where('transaction_id', 'TXN-LEDGER')->count());

        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => 'TXN-LEDGER',
            'account_id' => 'SENDER',
            'entry_type' => 'debit',
            'amount' => 100.00,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => 'TXN-LEDGER',
            'account_id' => 'RECEIVER',
            'entry_type' => 'credit',
            'amount' => 100.00,
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_transactions_idempotency()
    {
        $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-IDEM',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 100.00,
        ])->assertStatus(Response::HTTP_CREATED);

        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-IDEM',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 100.00,
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.idempotency_status', 'replayed')
            ->assertJsonPath('meta.idempotency.replayed', true);

        $this->getJson('/api/accounts/SENDER/balance')
            ->assertJsonPath('data.balance', '900.00');
    }

    #[Test]
    public function it_rejects_duplicate_transaction_id_when_payload_differs()
    {
        $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-CONFLICT',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 100.00,
        ])->assertStatus(Response::HTTP_CREATED);

        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-CONFLICT',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 125.00,
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonValidationErrors('transaction_id');
    }

    #[Test]
    public function it_prevents_transfer_exceeding_balance()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-OVER',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 9999.00,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->getJson('/api/accounts/SENDER/balance')
            ->assertJsonPath('data.balance', '1000.00');
    }

    #[Test]
    public function it_rejects_negative_amount()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-NEG',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => -100.00,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('amount');
    }

    #[Test]
    public function it_rejects_zero_amount()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-ZERO',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 0,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('amount');
    }

    #[Test]
    public function it_rejects_self_transfer()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-SELF',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'SENDER',
            'amount' => 100.00,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('to_account_id');
    }

    #[Test]
    public function it_rejects_transfer_from_nonexistent_account()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-GHOST',
            'from_account_id' => 'GHOST999',
            'to_account_id' => 'RECEIVER',
            'amount' => 100.00,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('from_account_id');
    }

    #[Test]
    public function it_rejects_transfer_without_transaction_id()
    {
        $response = $this->postJson('/api/transfers', [
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 100.00,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('transaction_id');
    }

    #[Test]
    public function it_normalizes_decimal_string_amounts_without_losing_cents()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-DECIMAL',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => '6.1',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.amount', '6.10');

        $this->getJson('/api/accounts/SENDER/balance')
            ->assertJsonPath('data.balance', '993.90');

        $this->getJson('/api/accounts/RECEIVER/balance')
            ->assertJsonPath('data.balance', '6.10');
    }

    #[Test]
    public function it_handles_exact_balance_transfer()
    {
        $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-EXACT',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 1000.00,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->getJson('/api/accounts/SENDER/balance')
            ->assertJsonPath('data.balance', '0.00');
    }

    #[Test]
    public function it_handles_multiple_sequential_transfers()
    {
        $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-SEQ1',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 300.00,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-SEQ2',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 200.00,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-SEQ3',
            'from_account_id' => 'RECEIVER',
            'to_account_id' => 'SENDER',
            'amount' => 100.00,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->getJson('/api/accounts/SENDER/balance')
            ->assertJsonPath('data.balance', '600.00');

        $this->getJson('/api/accounts/RECEIVER/balance')
            ->assertJsonPath('data.balance', '400.00');
    }

    #[Test]
    public function it_rejects_transfer_to_nonexistent_account()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-GHOST-TO',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'GHOST999',
            'amount' => 100.00,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('to_account_id');
    }

    #[Test]
    public function it_does_not_create_transfer_entries_when_insufficient_funds_transfer_is_rejected()
    {
        $response = $this->postJson('/api/transfers', [
            'transaction_id' => 'TXN-REJECTED',
            'from_account_id' => 'SENDER',
            'to_account_id' => 'RECEIVER',
            'amount' => 9999.00,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame(0, LedgerEntry::where('transaction_id', 'TXN-REJECTED')->count());
    }
}
