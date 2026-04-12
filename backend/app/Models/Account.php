<?php

namespace App\Models;

use App\Helpers\MoneyFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Account extends Model
{
    use HasFactory;

    protected $fillable = ['account_id', 'name'];

    /**
     * Scope: eager load ledger entry sums for balance calculation.
     * This allows us to derive balance without N+1 queries.
     */
    public function scopeWithDerivedBalance(Builder $query): Builder
    {
        return $query
            ->select('accounts.*')
            ->withSum([
                'ledgerEntries as credits_sum' => fn (Builder $ledgerEntries) => $ledgerEntries->credits(),
            ], 'amount')
            ->withSum([
                'ledgerEntries as debits_sum' => fn (Builder $ledgerEntries) => $ledgerEntries->debits(),
            ], 'amount');
    }

    /**
     * Calculate the current balance for a specific account from its ledger entries.
     */
    public static function calculateBalanceFor(string $accountId): string
    {
        $credits = LedgerEntry::where('account_id', $accountId)
            ->credits()
            ->sum('amount');

        $debits = LedgerEntry::where('account_id', $accountId)
            ->debits()
            ->sum('amount');

        return MoneyFormatter::signedDifference($credits, $debits);
    }

    /**
     * Get the route key for the model.
     * Allows route model binding using account_id instead of id.
     */
    public function getRouteKeyName(): string
    {
        return 'account_id';
    }

    /**
     * Get all ledger entries for this account.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id', 'account_id');
    }

    /**
     * Check if an account with the given ID exists and return it.
     * Throws ModelNotFoundException if not found.
     */
    public static function checkAccountID(string $account_id): Account
    {
        $account = Account::where('account_id', $account_id)->first();
        if (!$account) {
            throw new ModelNotFoundException("Account with id '{$account_id}' not found.");
        }

        return $account;
    }
}
