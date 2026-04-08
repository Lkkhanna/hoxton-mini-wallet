<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['account_id' => 'ACC001', 'name' => 'Alice Johnson'],
            ['account_id' => 'ACC002', 'name' => 'Bob Smith'],
            ['account_id' => 'ACC003', 'name' => 'Charlie Brown'],
        ];

        foreach ($accounts as $accountData) {
            Account::firstOrCreate(
                ['account_id' => $accountData['account_id']],
                $accountData
            );
        }

        if (LedgerEntry::count() > 0) {
            return;
        }

        $startedAt = now()->subDays(5)->startOfDay();

        $openingEntries = [
            [
                'transaction_id' => 'TXN-20260401-OPEN-001',
                'account_id' => 'ACC001',
                'amount' => '12000.00',
                'created_at' => $startedAt->copy()->addHours(9),
            ],
            [
                'transaction_id' => 'TXN-20260401-OPEN-002',
                'account_id' => 'ACC002',
                'amount' => '4000.00',
                'created_at' => $startedAt->copy()->addHours(9)->addMinutes(5),
            ],
            [
                'transaction_id' => 'TXN-20260401-OPEN-003',
                'account_id' => 'ACC003',
                'amount' => '6500.00',
                'created_at' => $startedAt->copy()->addHours(9)->addMinutes(10),
            ],
        ];

        foreach ($openingEntries as $entry) {
            LedgerEntry::create([
                'transaction_id' => $entry['transaction_id'],
                'account_id' => $entry['account_id'],
                'entry_type' => 'credit',
                'amount' => $entry['amount'],
                'counterparty_account_id' => $entry['account_id'],
                'description' => 'Initial funding',
                'created_at' => $entry['created_at'],
            ]);
        }

        $transfers = [
            [
                'transaction_id' => 'TXN-20260402-001',
                'from_account_id' => 'ACC001',
                'to_account_id' => 'ACC002',
                'amount' => '1500.00',
                'description' => 'Client allocation transfer',
                'created_at' => $startedAt->copy()->addDay()->addHours(11),
            ],
            [
                'transaction_id' => 'TXN-20260403-001',
                'from_account_id' => 'ACC003',
                'to_account_id' => 'ACC001',
                'amount' => '700.00',
                'description' => 'Portfolio rebalance',
                'created_at' => $startedAt->copy()->addDays(2)->addHours(10)->addMinutes(30),
            ],
            [
                'transaction_id' => 'TXN-20260404-001',
                'from_account_id' => 'ACC001',
                'to_account_id' => 'ACC003',
                'amount' => '1200.00',
                'description' => 'Advisory adjustment',
                'created_at' => $startedAt->copy()->addDays(3)->addHours(14),
            ],
            [
                'transaction_id' => 'TXN-20260405-001',
                'from_account_id' => 'ACC002',
                'to_account_id' => 'ACC003',
                'amount' => '500.00',
                'description' => 'Quarterly adjustment',
                'created_at' => $startedAt->copy()->addDays(4)->addHours(15)->addMinutes(15),
            ],
        ];

        foreach ($transfers as $transfer) {
            $this->seedTransfer($transfer);
        }
    }

    /**
     * Seed a debit and credit pair so seeded history follows the same ledger rules as live transfers.
     *
     * @param  array<string, mixed>  $transfer
     */
    protected function seedTransfer(array $transfer): void
    {
        LedgerEntry::create([
            'transaction_id' => $transfer['transaction_id'],
            'account_id' => $transfer['from_account_id'],
            'entry_type' => 'debit',
            'amount' => $transfer['amount'],
            'counterparty_account_id' => $transfer['to_account_id'],
            'description' => $transfer['description'],
            'created_at' => $transfer['created_at'],
        ]);

        LedgerEntry::create([
            'transaction_id' => $transfer['transaction_id'],
            'account_id' => $transfer['to_account_id'],
            'entry_type' => 'credit',
            'amount' => $transfer['amount'],
            'counterparty_account_id' => $transfer['from_account_id'],
            'description' => $transfer['description'],
            'created_at' => $transfer['created_at'],
        ]);
    }
}
