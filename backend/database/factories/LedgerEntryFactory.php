<?php

namespace Database\Factories;

use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Ledger entry factory for concise financial test setup.
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'transaction_id' => 'TXN-' . Str::upper(Str::random(10)),
            'account_id' => strtoupper($this->faker->bothify('ACC###')),
            'entry_type' => 'credit',
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'counterparty_account_id' => strtoupper($this->faker->bothify('ACC###')),
            'description' => 'Test ledger entry',
            'created_at' => now(),
        ];
    }

    public function credit(): self
    {
        return $this->state(fn (): array => [
            'entry_type' => 'credit',
        ]);
    }

    public function debit(): self
    {
        return $this->state(fn (): array => [
            'entry_type' => 'debit',
        ]);
    }
}
