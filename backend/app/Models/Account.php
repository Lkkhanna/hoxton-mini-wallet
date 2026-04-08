<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = ['account_id', 'name'];

    public function scopeWithDerivedBalance(Builder $query): Builder
    {
        return $query->leftJoin('ledger_entries', 'ledger_entries.account_id', '=', 'accounts.account_id')
            ->select(
                'accounts.id',
                'accounts.account_id',
                'accounts.name',
                'accounts.created_at',
                'accounts.updated_at'
            )
            ->selectRaw(" 
                COALESCE(SUM(CASE
                    WHEN ledger_entries.entry_type = 'credit' THEN ledger_entries.amount
                    WHEN ledger_entries.entry_type = 'debit' THEN -ledger_entries.amount
                    ELSE 0
                END), 0) as balance
            ")
            ->groupBy(
                'accounts.id',
                'accounts.account_id',
                'accounts.name',
                'accounts.created_at',
                'accounts.updated_at'
            );
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
    public function ledgerEntries()
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
            return $this->formatDecimalString($this->attributes['balance']);
        }

        $balance = $this->ledgerEntries()
            ->selectRaw(" 
                COALESCE(SUM(CASE 
                    WHEN entry_type = 'credit' THEN amount 
                    WHEN entry_type = 'debit' THEN -amount 
                    ELSE 0 
                END), 0) as balance
            ")
            ->value('balance');

        return $this->formatDecimalString($balance);
    }

    protected function formatDecimalString($value): string
    {
        $stringValue = trim((string) ($value ?? '0'));

        if ($stringValue === '') {
            return '0.00';
        }

        $negative = str_starts_with($stringValue, '-');
        $unsignedValue = ltrim($stringValue, '+-');

        if ($unsignedValue === '') {
            return '0.00';
        }

        [$wholePart, $fractionPart] = array_pad(explode('.', $unsignedValue, 2), 2, '');

        $wholePart = preg_replace('/\D/', '', $wholePart ?? '') ?? '';
        $fractionPart = preg_replace('/\D/', '', $fractionPart ?? '') ?? '';

        $wholePart = ltrim($wholePart, '0');
        $wholePart = $wholePart === '' ? '0' : $wholePart;
        $fractionPart = str_pad(substr($fractionPart, 0, 2), 2, '0');

        return ($negative ? '-' : '') . $wholePart . '.' . $fractionPart;
    }
}
