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
use Carbon\Carbon;

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
     * @param string $transactionId Unique ID for the transfer transaction
     * @param string $fromAccountId Sender's account ID
     * @param string $toAccountId Recipient's account ID
     * @param string $amount Transfer amount as a string (validated by TransferRequest)
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

            // Check for existing transfer
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

            // Perform the transfer within a transaction to ensure atomicity and consistency
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

                // Re-check for existing transfer after acquiring locks to ensure idempotency under concurrency
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

                // Check for sufficient funds before attempting to write any ledger entries
                if (bccomp($senderBalance, $normalizedAmount, MoneyFormatter::SCALE) < 0) {
                    throw new InsufficientFundsException(
                        $fromAccountId,
                        $senderBalance,
                        $normalizedAmount
                    );
                }

                $now = now();

                // Create ledger entry for debit
                $this->createLedgerEntry(
                    $transactionId, $fromAccountId, 'debit',
                    $normalizedAmount, $toAccountId, $now
                );

                // Create ledger entry for credit
                $this->createLedgerEntry(
                    $transactionId, $toAccountId, 'credit',
                    $normalizedAmount, $fromAccountId, $now
                );

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
     * Create a ledger entry for one side of the transfer.
     * 
     * @param string $transactionId Unique ID for the transfer transaction
     * @param string $accountId Account affected by this ledger entry
     * @param string $entryType 'debit' or 'credit'
     * @param string $amount
     * @param Carbon $now Timestamp for created_at
     */
    private function createLedgerEntry(
        string $transactionId,
        string $accountId,
        string $entryType,
        string $amount,
        string $counterpartyAccountId,
        Carbon $now
    ): void {
        $isDebit = $entryType === 'debit';

        LedgerEntry::create([
            'transaction_id'          => $transactionId,
            'account_id'              => $accountId,
            'entry_type'              => $entryType,
            'amount'                  => $amount,
            'counterparty_account_id' => $counterpartyAccountId,
            'description'             => $isDebit
                ? "Transfer to {$counterpartyAccountId}"
                : "Transfer from {$counterpartyAccountId}",
            'created_at'              => $now,
        ]);
    }

    /**
     * Calculate the current balance for an account from ledger entries only.
     * 
     * @param string $accountId
     * @return string Normalized two-decimal string representing the balance
     */
    public function calculateBalance(string $accountId): string
    {
        return Account::calculateBalanceFor($accountId);
    }

    /**
     * Normalize a validated amount into a two-decimal string for storage/response.
     * 
     * @param string $amount
     * @return string Normalized amount as a string with 2 decimal places
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
     * @param string $transactionId
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
     * @param array<string, mixed> $existingTransfer
     * @param string $transactionId
     * @param string $fromAccountId
     * @param string $toAccountId
     * @param string $amount Normalized two-decimal string
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
