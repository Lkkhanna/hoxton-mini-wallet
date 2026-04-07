<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'amount'     => 'decimal:2',
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
    public function scopeCredits($query)
    {
        return $query->where('entry_type', 'credit');
    }

    /**
     * Scope: filter by debit entries.
     */
    public function scopeDebits($query)
    {
        return $query->where('entry_type', 'debit');
    }
}
