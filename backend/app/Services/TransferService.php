<?php

namespace App\Services;

use App\Exceptions\DuplicateTransactionException;
use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Helpers\MoneyFormatter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Coordinates the atomic ledger workflow for transfers.
 */
class TransferService
{
    private const LEDGER_ENTRY_COUNT = 2;

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

        try {

            Log::info('Processing transfer request.', [
                'transaction_id' => $transactionId,
                'from_account_id' => $fromAccountId,
                'to_account_id' => $toAccountId,
                'amount' => $normalizedAmount,
            ]);

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

                if (bccomp($senderBalance, $normalizedAmount, MoneyFormatter::SCALE) < 0) {
                    throw new InsufficientFundsException(
                        $fromAccountId,
                        $senderBalance,
                        $normalizedAmount
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
        } catch (Throwable $e) {
            // PDO returns '23000' (string) for all integrity constraint violations.
            // We narrow to our specific idempotency index to avoid masking other
            // constraint failures (foreign keys, NOT NULL, etc.)
            if (
                $e->getCode() === '23000' &&
                str_contains($e->getMessage(), 'ledger_idempotency_unique')
            ) {
                Log::warning('Duplicate transaction detected at DB constraint level.', [
                    'transaction_id' => $transactionId,
                ]);

                $existing = $this->findTransferByTransactionId($transactionId);

                if ($existing) {
                    return $this->resolveExistingTransfer(
                        $existing,
                        $transactionId,
                        $fromAccountId,
                        $toAccountId,
                        $normalizedAmount
                    );
                }

                // Constraint fired but no committed rows found — two concurrent
                // requests with disjoint account pairs and same transaction_id.
                throw new DuplicateTransactionException($transactionId, true);
            }

            Log::error('Transfer failed unexpectedly.', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate the current balance for an account from ledger entries only.
     */
    public function calculateBalance(string $accountId): string
    {
        return Account::calculateBalanceFor($accountId);
    }

    /**
     * Normalize a validated amount into a two-decimal string for storage/response.
     */
    protected function normalizeAmount(string $amount): string
    {
        return MoneyFormatter::normalizeDecimalString($amount);
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

        if ($entries->count() !== self::LEDGER_ENTRY_COUNT) {
            throw new RuntimeException('Ledger integrity violation detected for this transaction ID.');
        }

        $debitEntry = $entries->firstWhere('entry_type', 'debit');
        $creditEntry = $entries->firstWhere('entry_type', 'credit');

        if (!$debitEntry || !$creditEntry) {
            throw new RuntimeException('Ledger integrity violation detected for this transaction ID.');
        }

        $debitAmount = MoneyFormatter::normalizeDecimalString($debitEntry->amount);
        $creditAmount = MoneyFormatter::normalizeDecimalString($creditEntry->amount);

        if (
            bccomp($debitAmount, $creditAmount, MoneyFormatter::SCALE) !== 0
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
            || bccomp($existingTransfer['amount'], $amount, MoneyFormatter::SCALE) !== 0
        ) {
            throw new DuplicateTransactionException($transactionId, true);
        }

        return $existingTransfer;
    }
}
