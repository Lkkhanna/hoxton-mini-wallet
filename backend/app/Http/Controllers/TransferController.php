<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferRequest;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles transfer submission endpoints.
 */
class TransferController extends Controller
{
    protected TransferService $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * Execute a money transfer between two accounts.
     *
     * The transfer is:
     * - Atomic (both debit and credit succeed or fail together)
     * - Idempotent (repeated identical transaction_id returns the original result)
     * - Consistent (balance checked under row-level lock)
     */
    public function store(TransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->transferService->transfer(
                $validated['transaction_id'],
                $validated['from_account_id'],
                $validated['to_account_id'],
                (string) $validated['amount']
            );

            $isReplay = ($result['idempotency_status'] ?? 'created') === 'replayed';

            Log::info('Transfer request handled successfully.', [
                'transaction_id' => $validated['transaction_id'],
                'from_account_id' => $validated['from_account_id'],
                'to_account_id' => $validated['to_account_id'],
                'amount' => (string) $validated['amount'],
                'idempotency_status' => $result['idempotency_status'] ?? 'created',
            ]);

            return $this->successResponse(
                $result,
                $isReplay
                    ? 'Transfer already existed. Returning the original result.'
                    : 'Transfer completed successfully.',
                $isReplay ? 200 : 201,
                [
                    'idempotency' => [
                        'replayed' => $isReplay,
                    ],
                ]
            );
        } catch (Throwable $e) {
            Log::error('Transfer request failed.', [
                'transaction_id' => $validated['transaction_id'] ?? null,
                'from_account_id' => $validated['from_account_id'] ?? null,
                'to_account_id' => $validated['to_account_id'] ?? null,
                'amount' => isset($validated['amount']) ? (string) $validated['amount'] : null,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->exceptionResponse($e, 'Transfer request failed.');
        }
    }
}
