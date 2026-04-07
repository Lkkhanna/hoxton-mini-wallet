<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Http\Requests\CreateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\LedgerEntryResource;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountController extends Controller
{
    protected TransferService $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * List all accounts with their current balances.
     *
     * GET /api/accounts
     */
    public function index(): JsonResponse
    {
        try {
            $accounts = Account::withDerivedBalance()
                ->orderByDesc('accounts.created_at')
                ->get();

            Log::info('Accounts retrieved successfully.', [
                'account_count' => $accounts->count(),
            ]);

            return $this->successResponse(
                AccountResource::collection($accounts)->resolve(),
                'Accounts retrieved successfully.'
            );
        } catch (Throwable $e) {
            Log::error('Failed to retrieve accounts.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->exceptionResponse(
                $e,
                'Failed to retrieve accounts.'
            );
        }
    }

    /**
     * Create a new account.
     *
     * POST /api/accounts
     */
    public function store(CreateAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $account = Account::create($validated);

            Log::info('Account created successfully.', [
                'account_id' => $account->account_id,
            ]);

            return $this->successResponse(
                (new AccountResource($account))->resolve(),
                'Account created successfully.',
                201
            );
        } catch (Throwable $e) {
            Log::error('Failed to create account.', [
                'account_id' => $validated['account_id'] ?? null,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->exceptionResponse($e, 'Failed to create account.');
        }
    }

    /**
     * Get the current balance for an account.
     * Balance is derived from the sum of all ledger entries.
     *
     * GET /api/accounts/{account_id}/balance
     */
    public function balance(string $accountId): JsonResponse
    {
        try {
            $account = Account::where('account_id', $accountId)->firstOrFail();

            $balance = $this->transferService->calculateBalance($accountId);

            Log::info('Account balance retrieved successfully.', [
                'account_id' => $accountId,
                'balance' => $balance,
            ]);

            return $this->successResponse(
                [
                    'account_id' => $account->account_id,
                    'balance'    => $balance,
                ],
                'Account balance retrieved successfully.'
            );
        } catch (Throwable $e) {
            Log::error('Failed to retrieve account balance.', [
                'account_id' => $accountId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->exceptionResponse($e, 'Failed to retrieve account balance.');
        }
    }

    /**
     * Get transaction history for an account.
     * Returns ledger entries ordered by most recent first.
     *
     * GET /api/accounts/{account_id}/transactions
     */
    public function transactions(string $accountId): JsonResponse
    {
        try {
            Account::where('account_id', $accountId)->firstOrFail();

            $entries = LedgerEntry::where('account_id', $accountId)
                ->select([
                    'id',
                    'transaction_id',
                    'entry_type',
                    'amount',
                    'counterparty_account_id',
                    'description',
                    'created_at',
                ])
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->limit(100)
                ->get();

            Log::info('Transaction history retrieved successfully.', [
                'account_id' => $accountId,
                'transaction_count' => $entries->count(),
            ]);

            return $this->successResponse(
                LedgerEntryResource::collection($entries)->resolve(),
                'Transaction history retrieved successfully.'
            );
        } catch (Throwable $e) {
            Log::error('Failed to retrieve transaction history.', [
                'account_id' => $accountId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->exceptionResponse($e, 'Failed to retrieve transaction history.');
        }
    }
}
