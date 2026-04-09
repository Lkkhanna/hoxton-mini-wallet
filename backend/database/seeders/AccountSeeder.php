<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->accounts() as $account) {
            Account::firstOrCreate(
                ['account_id' => $account['account_id']],
                $account
            );
        }

        if (LedgerEntry::count() > 0) {
            return;
        }

        $startedAt = now()->subDays(8)->startOfDay();

        foreach ($this->openingEntries($startedAt) as $entry) {
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

        foreach ($this->transfers($startedAt) as $transfer) {
            LedgerEntry::create([
                'transaction_id' => $transfer['transaction_id'],
                'account_id' => $transfer['from_account_id'],
                'entry_type' => 'debit',
                'amount' => $transfer['amount'],
                'counterparty_account_id' => $transfer['to_account_id'],
                'description' => sprintf('Transfer to %s', $transfer['to_account_id']),
                'created_at' => $transfer['created_at'],
            ]);

            LedgerEntry::create([
                'transaction_id' => $transfer['transaction_id'],
                'account_id' => $transfer['to_account_id'],
                'entry_type' => 'credit',
                'amount' => $transfer['amount'],
                'counterparty_account_id' => $transfer['from_account_id'],
                'description' => sprintf('Transfer from %s', $transfer['from_account_id']),
                'created_at' => $transfer['created_at'],
            ]);
        }
    }

    protected function accounts(): array
    {
        return [
            ['account_id' => 'ACC001', 'name' => 'Alice Johnson'],
            ['account_id' => 'ACC002', 'name' => 'Bob Smith'],
            ['account_id' => 'ACC003', 'name' => 'Charlie Brown'],
        ];
    }

    protected function openingEntries($startedAt): array
    {
        return [
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
    }

    protected function transfers($startedAt): array
    {
        return [
            [
                'transaction_id' => 'TXN-20260402-001',
                'from_account_id' => 'ACC001',
                'to_account_id' => 'ACC002',
                'amount' => '1500.00',
                'created_at' => $startedAt->copy()->addDay()->addHours(11),
            ],
            [
                'transaction_id' => 'TXN-20260403-001',
                'from_account_id' => 'ACC003',
                'to_account_id' => 'ACC001',
                'amount' => '700.00',
                'created_at' => $startedAt->copy()->addDays(2)->addHours(10)->addMinutes(30),
            ],
            [
                'transaction_id' => 'TXN-20260404-001',
                'from_account_id' => 'ACC001',
                'to_account_id' => 'ACC003',
                'amount' => '1200.00',
                'created_at' => $startedAt->copy()->addDays(3)->addHours(14),
            ],
            [
                'transaction_id' => 'TXN-20260405-001',
                'from_account_id' => 'ACC002',
                'to_account_id' => 'ACC003',
                'amount' => '500.00',
                'created_at' => $startedAt->copy()->addDays(4)->addHours(15)->addMinutes(15),
            ],
            [
                'transaction_id' => 'TXN-20260406-001',
                'from_account_id' => 'ACC001',
                'to_account_id' => 'ACC002',
                'amount' => '220.00',
                'created_at' => $startedAt->copy()->addDays(5)->addHours(9)->addMinutes(10),
            ],
            [
                'transaction_id' => 'TXN-20260406-002',
                'from_account_id' => 'ACC002',
                'to_account_id' => 'ACC001',
                'amount' => '220.00',
                'created_at' => $startedAt->copy()->addDays(5)->addHours(10)->addMinutes(5),
            ],
            [
                'transaction_id' => 'TXN-20260406-003',
                'from_account_id' => 'ACC001',
                'to_account_id' => 'ACC003',
                'amount' => '180.00',
                'created_at' => $startedAt->copy()->addDays(5)->addHours(11)->addMinutes(20),
            ],
            [
                'transaction_id' => 'TXN-20260406-004',
                'from_account_id' => 'ACC003',
                'to_account_id' => 'ACC001',
                'amount' => '180.00',
                'created_at' => $startedAt->copy()->addDays(5)->addHours(12)->addMinutes(15),
            ],
            [
                'transaction_id' => 'TXN-20260407-001',
                'from_account_id' => 'ACC001',
                'to_account_id' => 'ACC002',
                'amount' => '310.00',
                'created_at' => $startedAt->copy()->addDays(6)->addHours(9)->addMinutes(45),
            ],
            [
                'transaction_id' => 'TXN-20260407-002',
                'from_account_id' => 'ACC002',
                'to_account_id' => 'ACC001',
                'amount' => '310.00',
                'created_at' => $startedAt->copy()->addDays(6)->addHours(11),
            ],
            [
                'transaction_id' => 'TXN-20260407-003',
                'from_account_id' => 'ACC001',
                'to_account_id' => 'ACC003',
                'amount' => '145.00',
                'created_at' => $startedAt->copy()->addDays(6)->addHours(13)->addMinutes(10),
            ],
            [
                'transaction_id' => 'TXN-20260407-004',
                'from_account_id' => 'ACC003',
                'to_account_id' => 'ACC001',
                'amount' => '145.00',
                'created_at' => $startedAt->copy()->addDays(6)->addHours(14)->addMinutes(25),
            ],
        ];
    }
}
