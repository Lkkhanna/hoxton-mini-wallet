<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\LedgerEntryResource;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Services\AccountService;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles account-oriented API endpoints such as listing, creation, balance,
 * and transaction history.
 */
class AccountController extends Controller
{
    public function __construct(
        protected TransferService $transferService,
        protected AccountService $accountService
    ) {
    }

    /**
     * Return the list of accounts with derived balances.
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
     * Create a new wallet account.
     *
     * @param CreateAccountRequest $request
     * @return JsonResponse
     */
    public function store(CreateAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $account = $this->accountService->createAccount($validated);

            Log::info('Account created successfully.', [
                'account_id' => $account->account_id,
            ]);

            return $this->successResponse(
                (new AccountResource($account))->resolve(),
                'Account created successfully.',
                Response::HTTP_CREATED
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
     * Return the balance for a single account.
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
                    'balance' => $balance,
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
     * Return paginated transaction history for a single account.
     */
    public function transactions(Request $request, string $accountId): JsonResponse
    {
        try {
            Account::where('account_id', $accountId)->firstOrFail();

            $perPage = (int) $request->query('per_page', 10);
            $perPage = max(1, min($perPage, 50));

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
                ->paginate($perPage);

            Log::info('Transaction history retrieved successfully.', [
                'account_id' => $accountId,
                'transaction_count' => $entries->count(),
                'page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
            ]);

            return $this->successResponse(
                LedgerEntryResource::collection($entries->items())->resolve(),
                'Transaction history retrieved successfully.',
                Response::HTTP_OK,
                [
                    'pagination' => [
                        'current_page' => $entries->currentPage(),
                        'last_page' => $entries->lastPage(),
                        'per_page' => $entries->perPage(),
                        'total' => $entries->total(),
                        'from' => $entries->firstItem(),
                        'to' => $entries->lastItem(),
                    ],
                ]
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
