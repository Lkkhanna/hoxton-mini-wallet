<?php

namespace App\Services;

use App\Exceptions\DuplicateTransactionException;
use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Coordinates the atomic ledger workflow for transfers.
 */
class TransferService
{
    /**
     * Execute an atomic, idempotent money transfer between two accounts.
     *
     * Critical guarantees:
     * 1. ATOMICITY    - Both debit and credit succeed or fail together.
     * 2. IDEMPOTENCY  - Duplicate transaction_id returns an existing result.
     * 3. CONSISTENCY  - Balance is derived from the ledger under row-level lock.
     * 4. DEADLOCK-FREE - Accounts are locked in deterministic order.
     *
     * @return array<string, mixed>
     *
     * @throws DuplicateTransactionException
     * @throws InsufficientFundsException
     * @throws ModelNotFoundException
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

        // Fast-path idempotency check before taking locks.
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

        return DB::transaction(function () use ($transactionId, $fromAccountId, $toAccountId, $normalizedAmount) {
            // Lock the involved accounts in sorted order to reduce deadlock risk.
            $sortedIds = collect([$fromAccountId, $toAccountId])->sort()->values();

            $accounts = Account::whereIn('account_id', $sortedIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('account_id');

            if (!$accounts->has($fromAccountId)) {
                throw (new ModelNotFoundException())->setModel(Account::class, [$fromAccountId]);
            }

            if (!$accounts->has($toAccountId)) {
                throw (new ModelNotFoundException())->setModel(Account::class, [$toAccountId]);
            }

            // Re-check inside the transaction so concurrent duplicate requests stay safe.
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

            $senderBalance = $this->calculateBalance($fromAccountId);

            if (bccomp($senderBalance, $normalizedAmount, 2) < 0) {
                throw new InsufficientFundsException(
                    $fromAccountId,
                    (float) $senderBalance,
                    (float) $normalizedAmount
                );
            }

            $now = now();

            LedgerEntry::create([
                'transaction_id' => $transactionId,
                'account_id' => $fromAccountId,
                'entry_type' => 'debit',
                'amount' => $normalizedAmount,
                'counterparty_account_id' => $toAccountId,
                'description' => "Transfer to {$toAccountId}",
                'created_at' => $now,
            ]);

            LedgerEntry::create([
                'transaction_id' => $transactionId,
                'account_id' => $toAccountId,
                'entry_type' => 'credit',
                'amount' => $normalizedAmount,
                'counterparty_account_id' => $fromAccountId,
                'description' => "Transfer from {$fromAccountId}",
                'created_at' => $now,
            ]);

            Log::info('Transfer completed successfully.', [
                'transaction_id' => $transactionId,
                'from_account_id' => $fromAccountId,
                'to_account_id' => $toAccountId,
                'amount' => $normalizedAmount,
            ]);

            return [
                'transaction_id' => $transactionId,
                'from_account_id' => $fromAccountId,
                'to_account_id' => $toAccountId,
                'amount' => $normalizedAmount,
                'status' => 'completed',
                'idempotency_status' => 'created',
                'created_at' => $now->toIso8601String(),
            ];
        });
    }

    /**
     * Calculate the current balance for an account from ledger entries only.
     */
    public function calculateBalance(string $accountId): string
    {
        $balance = LedgerEntry::where('account_id', $accountId)
            ->selectRaw(
                "
                    COALESCE(SUM(CASE
                        WHEN entry_type = 'credit' THEN amount
                        WHEN entry_type = 'debit' THEN -amount
                        ELSE 0
                    END), 0) as balance
                "
            )
            ->value('balance');

        return $this->formatDecimalString($balance);
    }

    /**
     * Normalize a validated amount into a two-decimal string for storage/response.
     */
    protected function normalizeAmount(string $amount): string
    {
        return $this->formatDecimalString($amount);
    }

    /**
     * Reconstruct a prior transfer by transaction ID for idempotent replay.
     *
     * Returns null when the transfer does not exist yet.
     *
     * @return array<string, mixed>|null
     */
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

        if ($entries->count() !== 2) {
            throw new RuntimeException('Ledger integrity violation detected for this transaction ID.');
        }

        $debitEntry = $entries->firstWhere('entry_type', 'debit');
        $creditEntry = $entries->firstWhere('entry_type', 'credit');

        if (!$debitEntry || !$creditEntry) {
            throw new RuntimeException('Ledger integrity violation detected for this transaction ID.');
        }

        $debitAmount = $this->formatDecimalString($debitEntry->amount);
        $creditAmount = $this->formatDecimalString($creditEntry->amount);

        if (
            bccomp($debitAmount, $creditAmount, 2) !== 0
            || $debitEntry->counterparty_account_id !== $creditEntry->account_id
            || $creditEntry->counterparty_account_id !== $debitEntry->account_id
        ) {
            throw new RuntimeException('Ledger integrity violation detected for this transaction ID.');
        }

        return [
            'transaction_id' => $transactionId,
            'from_account_id' => $debitEntry->account_id,
            'to_account_id' => $creditEntry->account_id,
            'amount' => $debitAmount,
            'status' => 'completed',
            'idempotency_status' => 'replayed',
            'created_at' => optional($debitEntry->created_at)->toIso8601String(),
        ];
    }

    /**
     * Validate that an existing idempotent transfer matches the incoming payload.
     *
     * @param  array<string, mixed>  $existingTransfer
     * @return array<string, mixed>
     *
     * @throws DuplicateTransactionException
     */
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

    /**
     * Keep ledger money values as decimal strings instead of converting through floats.
     */
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
