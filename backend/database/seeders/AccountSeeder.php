<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo accounts (idempotent — skip if already exist)
        $accounts = [
            ['account_id' => 'ACC001', 'name' => 'Alice Johnson'],
            ['account_id' => 'ACC002', 'name' => 'Bob Smith'],
            ['account_id' => 'ACC003', 'name' => 'Charli Brown'],
        ];

        foreach ($accounts as $accountData) {
            Account::firstOrCreate(
                ['account_id' => $accountData['account_id']],
                $accountData
            );
        }

        // Seed initial balances via ledger entries (system credit)
        // Only seed if no ledger entries exist yet
        if (LedgerEntry::count() === 0) {
            $initialBalances = [
                ['account_id' => 'ACC001', 'amount' => 10000.00],
                ['account_id' => 'ACC002', 'amount' => 5000.00],
                ['account_id' => 'ACC003', 'amount' => 7500.00],
            ];

            foreach ($initialBalances as $balance) {
                LedgerEntry::create([
                    'transaction_id'          => 'SEED-' . $balance['account_id'],
                    'account_id'              => $balance['account_id'],
                    'entry_type'              => 'credit',
                    'amount'                  => $balance['amount'],
                    'counterparty_account_id' => $balance['account_id'], // System deposit
                    'description'             => 'Initial deposit (seed)',
                ]);
            }
        }
    }
}
