<?php

namespace App\Models;

use App\Helpers\MoneyFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Derive balance from ledger entries.
     * Balance = SUM(credits) - SUM(debits)
     *
     * This is the ONLY source of truth for balance.
     * We never store balance as a mutable field.
     */
    public function getBalanceAttribute(): string
    {
        if (array_key_exists('balance', $this->attributes)) {
            return MoneyFormatter::normalizeDecimalString($this->attributes['balance']);
        }

        $credits = array_key_exists('credits_sum', $this->attributes)
            ? $this->attributes['credits_sum']
            : $this->ledgerEntries()->credits()->sum('amount');

        $debits = array_key_exists('debits_sum', $this->attributes)
            ? $this->attributes['debits_sum']
            : $this->ledgerEntries()->debits()->sum('amount');

        return MoneyFormatter::signedDifference($credits, $debits);
    }
}
