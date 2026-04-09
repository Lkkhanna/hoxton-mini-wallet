<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_can_create_an_account()
    {
        $response = $this->postJson('/api/accounts', [
            'account_id' => 'TEST001',
            'name' => 'Test User',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.account_id', 'TEST001')
            ->assertJsonPath('data.name', 'Test User')
            ->assertJsonPath('data.balance', '0.00');

        $this->assertDatabaseHas('accounts', ['account_id' => 'TEST001']);
    }

    #[Test]
    public function it_normalizes_account_creation_input()
    {
        $response = $this->postJson('/api/accounts', [
            'account_id' => ' acc-001 ',
            'name' => '  Test User  ',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.account_id', 'ACC-001')
            ->assertJsonPath('data.name', 'Test User');

        $this->assertDatabaseHas('accounts', [
            'account_id' => 'ACC-001',
            'name' => 'Test User',
        ]);
    }

    #[Test]
    public function it_returns_conflict_when_creating_a_duplicate_account_id()
    {
        Account::factory()->withAccountId('DUP001')->create(['name' => 'First']);

        $response = $this->postJson('/api/accounts', [
            'account_id' => 'dup001',
            'name' => 'Second',
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.account_id', 'DUP001')
            ->assertJsonPath('errors.account_id.0', "Account 'DUP001' already exists.");
    }

    #[Test]
    public function it_rejects_invalid_account_id_format()
    {
        $response = $this->postJson('/api/accounts', [
            'account_id' => 'invalid account!@#',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('account_id');
    }

    #[Test]
    public function it_returns_balance_as_zero_for_new_account()
    {
        Account::factory()->withAccountId('ZERO001')->create(['name' => 'Zero Balance']);

        $response = $this->getJson('/api/accounts/ZERO001/balance');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.account_id', 'ZERO001')
            ->assertJsonPath('data.balance', '0.00');
    }

    #[Test]
    public function it_returns_correct_balance_from_ledger()
    {
        Account::factory()->withAccountId('BAL001')->create(['name' => 'Balance Test']);
        Account::factory()->withAccountId('BAL002')->create(['name' => 'Counterparty']);

        LedgerEntry::factory()->credit()->create([
            'transaction_id' => 'TXN-BAL-1',
            'account_id' => 'BAL001',
            'amount' => 1000.00,
            'counterparty_account_id' => 'BAL002',
        ]);

        LedgerEntry::factory()->debit()->create([
            'transaction_id' => 'TXN-BAL-2',
            'account_id' => 'BAL001',
            'amount' => 250.00,
            'counterparty_account_id' => 'BAL002',
        ]);

        $response = $this->getJson('/api/accounts/BAL001/balance');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.balance', '750.00');
    }

    #[Test]
    public function it_returns_404_for_nonexistent_account_balance()
    {
        $response = $this->getJson('/api/accounts/GHOST999/balance');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function it_returns_transaction_history()
    {
        Account::factory()->withAccountId('HIST001')->create(['name' => 'History Test']);
        Account::factory()->withAccountId('HIST002')->create(['name' => 'Counterparty']);

        LedgerEntry::factory()->credit()->create([
            'transaction_id' => 'TXN-H1',
            'account_id' => 'HIST001',
            'amount' => 500.00,
            'counterparty_account_id' => 'HIST002',
            'description' => 'Test credit',
        ]);

        $response = $this->getJson('/api/accounts/HIST001/transactions');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'credit')
            ->assertJsonPath('data.0.amount', '500.00')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    #[Test]
    public function it_paginates_transaction_history()
    {
        Account::factory()->withAccountId('PAGE001')->create(['name' => 'Paged History']);
        Account::factory()->withAccountId('PAGE002')->create(['name' => 'Counterparty']);

        foreach (range(1, 12) as $index) {
            LedgerEntry::factory()
                ->state([
                    'transaction_id' => 'TXN-PAGE-' . $index,
                    'account_id' => 'PAGE001',
                    'entry_type' => $index % 2 === 0 ? 'debit' : 'credit',
                    'amount' => 10 + $index,
                    'counterparty_account_id' => 'PAGE002',
                    'description' => 'Paged entry ' . $index,
                    'created_at' => now()->subMinutes($index),
                ])
                ->create();
        }

        $response = $this->getJson('/api/accounts/PAGE001/transactions?page=2&per_page=5');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.last_page', 3)
            ->assertJsonPath('meta.pagination.per_page', 5)
            ->assertJsonPath('meta.pagination.total', 12);
    }

    #[Test]
    public function it_lists_all_accounts()
    {
        Account::factory()->withAccountId('LIST001')->create(['name' => 'First']);
        Account::factory()->withAccountId('LIST002')->create(['name' => 'Second']);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment(['account_id' => 'LIST001'])
            ->assertJsonFragment(['account_id' => 'LIST002']);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_account_transaction_history()
    {
        $response = $this->getJson('/api/accounts/GHOST999/transactions');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
