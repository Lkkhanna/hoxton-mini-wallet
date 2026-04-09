<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = ['account_id', 'name'];

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
            return $this->formatDecimalString($this->attributes['balance']);
        }

        $credits = array_key_exists('credits_sum', $this->attributes)
            ? $this->attributes['credits_sum']
            : $this->ledgerEntries()->credits()->sum('amount');

        $debits = array_key_exists('debits_sum', $this->attributes)
            ? $this->attributes['debits_sum']
            : $this->ledgerEntries()->debits()->sum('amount');

        return $this->formatSignedDecimalDifference($credits, $debits);
    }

    protected function formatSignedDecimalDifference(mixed $credits, mixed $debits): string
    {
        $normalizedCredits = $this->formatDecimalString($credits);
        $normalizedDebits = $this->formatDecimalString($debits);

        $comparison = bccomp($normalizedCredits, $normalizedDebits, 2);

        if ($comparison === 0) {
            return '0.00';
        }

        if ($comparison > 0) {
            return bcsub($normalizedCredits, $normalizedDebits, 2);
        }

        return '-' . bcsub($normalizedDebits, $normalizedCredits, 2);
    }

    protected function formatDecimalString(mixed $value): string
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
