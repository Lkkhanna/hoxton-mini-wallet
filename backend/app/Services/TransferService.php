<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\DuplicateTransactionException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferService
{
    /**
     * Execute an atomic, idempotent money transfer between two accounts.
     *
     * Critical guarantees:
     * 1. ATOMICITY    — Both debit and credit succeed or fail together (DB transaction)
     * 2. IDEMPOTENCY  — Duplicate transaction_id returns existing result, no double-processing
     * 3. CONSISTENCY  — Balance derived from ledger SUM, checked under row-level lock
     * 4. DEADLOCK-FREE — Accounts locked in deterministic (sorted) order
     *
     * @param  string $transactionId  Client-generated unique ID for idempotency
     * @param  string $fromAccountId  Sender account_id
     * @param  string $toAccountId    Receiver account_id
     * @param  string  $amount         Transfer amount (must be > 0)
     * @return array  Transfer result with status
     *
     * @throws DuplicateTransactionException  If transaction_id was already processed
     * @throws InsufficientFundsException     If sender has insufficient balance
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If account not found
     */
    public function transfer(
        string $transactionId,
        string $fromAccountId,
        string $toAccountId,
        string $amount
    ): array {
        $normalizedAmount = $this->normalizeAmount($amount);

        Log::info('Processing transfer request.', [
            'transaction_id' => $transactionId,
            'from_account_id' => $fromAccountId,
            'to_account_id' => $toAccountId,
            'amount' => $normalizedAmount,
        ]);

        // ── Step 1: Quick idempotency check (before acquiring locks) ──
        $existingTransfer = $this->findTransferByTransactionId($transactionId);
        if ($existingTransfer !== null) {
            Log::info('Duplicate transaction detected before lock acquisition.', [
                'transaction_id' => $transactionId,
            ]);
            return $this->resolveExistingTransfer(
                $existingTransfer,
                $transactionId,
                $fromAccountId,
                $toAccountId,
                $normalizedAmount
            );
        }

        // ── Step 2: Execute within a serializable database transaction ──
        return DB::transaction(function () use ($transactionId, $fromAccountId, $toAccountId, $normalizedAmount) {

            // Lock accounts in deterministic order to prevent deadlocks.
            // Always lock the "smaller" account_id first.
            $sortedIds = collect([$fromAccountId, $toAccountId])->sort()->values();

            $accounts = Account::whereIn('account_id', $sortedIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('account_id');

            // Validate both accounts exist
            if (!$accounts->has($fromAccountId)) {
                throw (new ModelNotFoundException())->setModel(Account::class, [$fromAccountId]);
            }
            if (!$accounts->has($toAccountId)) {
                throw (new ModelNotFoundException())->setModel(Account::class, [$toAccountId]);
            }

            // ── Step 3: Re-check idempotency INSIDE the transaction ──
            // This is critical for race conditions where two identical requests
            // pass the pre-lock check simultaneously.
            $existingTransfer = $this->findTransferByTransactionId($transactionId);
            if ($existingTransfer !== null) {
                Log::info('Duplicate transaction detected after lock acquisition.', [
                    'transaction_id' => $transactionId,
                ]);
                return $this->resolveExistingTransfer(
                    $existingTransfer,
                    $transactionId,
                    $fromAccountId,
                    $toAccountId,
                    $normalizedAmount
                );
            }

            // ── Step 4: Calculate sender's balance from ledger ──
            $senderBalance = $this->calculateBalance($fromAccountId);

            if (bccomp($senderBalance, $normalizedAmount, 2) < 0) {
                throw new InsufficientFundsException(
                    $fromAccountId,
                    (float) $senderBalance,
                    (float) $normalizedAmount
                );
            }

            // ── Step 5: Create atomic ledger entries (debit + credit) ──
            $now = now();

            LedgerEntry::create([
                'transaction_id'         => $transactionId,
                'account_id'             => $fromAccountId,
                'entry_type'             => 'debit',
                'amount'                 => $normalizedAmount,
                'counterparty_account_id'=> $toAccountId,
                'description'            => "Transfer to {$toAccountId}",
                'created_at'             => $now,
            ]);

            LedgerEntry::create([
                'transaction_id'         => $transactionId,
                'account_id'             => $toAccountId,
                'entry_type'             => 'credit',
                'amount'                 => $normalizedAmount,
                'counterparty_account_id'=> $fromAccountId,
                'description'            => "Transfer from {$fromAccountId}",
                'created_at'             => $now,
            ]);

            Log::info('Transfer completed successfully.', [
                'transaction_id' => $transactionId,
                'from_account_id' => $fromAccountId,
                'to_account_id' => $toAccountId,
                'amount' => $normalizedAmount,
            ]);

            return [
                'transaction_id'  => $transactionId,
                'from_account_id' => $fromAccountId,
                'to_account_id'   => $toAccountId,
                'amount'          => $normalizedAmount,
                'status'          => 'completed',
                'idempotency_status' => 'created',
                'created_at'      => $now->toIso8601String(),
            ];
        });
    }

    /**
     * Calculate account balance from ledger entries.
     * This is the single source of truth for balance.
     *
     * @param  string $accountId
     * @return float
     */
    public function calculateBalance(string $accountId): string
    {
        return number_format((float) LedgerEntry::where('account_id', $accountId)
            ->selectRaw("
                COALESCE(SUM(CASE 
                    WHEN entry_type = 'credit' THEN amount 
                    WHEN entry_type = 'debit' THEN -amount 
                    ELSE 0 
                END), 0) as balance
            ")
            ->value('balance'), 2, '.', '');
    }

    protected function normalizeAmount(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function findTransferByTransactionId(string $transactionId): ?array
    {
        $entries = LedgerEntry::where('transaction_id', $transactionId)
            ->select([
                'id',
                'transaction_id',
                'account_id',
                'entry_type',
                'amount',
                'counterparty_account_id',
                'created_at',
            ])
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $debitEntry = $entries->firstWhere('entry_type', 'debit');
        $creditEntry = $entries->firstWhere('entry_type', 'credit');
        $referenceEntry = $debitEntry ?? $creditEntry;

        return [
            'transaction_id' => $transactionId,
            'from_account_id' => $debitEntry?->account_id ?? $creditEntry?->counterparty_account_id,
            'to_account_id' => $creditEntry?->account_id ?? $debitEntry?->counterparty_account_id,
            'amount' => number_format((float) $referenceEntry?->amount, 2, '.', ''),
            'status' => 'completed',
            'idempotency_status' => 'replayed',
            'created_at' => optional($referenceEntry?->created_at)->toIso8601String(),
        ];
    }

    protected function resolveExistingTransfer(
        array $existingTransfer,
        string $transactionId,
        string $fromAccountId,
        string $toAccountId,
        string $amount
    ): array {
        if (
            $existingTransfer['from_account_id'] !== $fromAccountId
            || $existingTransfer['to_account_id'] !== $toAccountId
            || bccomp($existingTransfer['amount'], $amount, 2) !== 0
        ) {
            throw new DuplicateTransactionException($transactionId, true);
        }

        return $existingTransfer;
    }
}
