<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // Ledger entries are immutable — no updates

    protected $fillable = [
        'transaction_id',
        'account_id',
        'entry_type',
        'amount',
        'counterparty_account_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /**
     * A ledger entry belongs to an account.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }

    /**
     * The counterparty account involved in this transaction.
     */
    public function counterpartyAccount()
    {
        return $this->belongsTo(Account::class, 'counterparty_account_id', 'account_id');
    }

    /**
     * Scope: filter by credit entries.
     */
    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('entry_type', 'credit');
    }

    /**
     * Scope: filter by debit entries.
     */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('entry_type', 'debit');
    }

    /**
     * Scope: build the standard account history query used by the transactions endpoint.
     */
    public function scopeForAccountHistory(Builder $query, string $accountId): Builder
    {
        return $query->where('account_id', $accountId)
            ->select([
                'id',
                'transaction_id',
                'entry_type',
                'amount',
                'counterparty_account_id',
                'description',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
