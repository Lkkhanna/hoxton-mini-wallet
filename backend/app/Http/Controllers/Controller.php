<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountAlreadyExistsException;
use App\Exceptions\DuplicateTransactionException;
use App\Exceptions\InsufficientFundsException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Shared API response helpers used by JSON controllers.
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Build the standard success response envelope.
     */
    protected function successResponse(
        mixed $data,
        string $message,
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Build the standard error response envelope.
     */
    protected function errorResponse(
        string $message,
        int $status,
        array $errors = [],
        array $data = []
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Map known domain/framework exceptions to the API response contract.
     *
     * Validation and business-rule failures are translated into predictable client
     * responses, while unexpected failures fall back to a generic 500 payload.
     */
    protected function exceptionResponse(
        Throwable $e,
        string $fallbackMessage = 'An unexpected server error occurred.'
    ): JsonResponse {
        if ($e instanceof ValidationException) {
            return $this->errorResponse(
                'The given data was invalid.',
                $e->status,
                $e->errors()
            );
        }

        if ($e instanceof AccountAlreadyExistsException) {
            return $this->errorResponse(
                $e->getMessage(),
                409,
                ['account_id' => [$e->getMessage()]],
                ['account_id' => $e->getAccountId()]
            );
        }

        if ($e instanceof InsufficientFundsException) {
            return $this->errorResponse(
                $e->getMessage(),
                422,
                ['amount' => [$e->getMessage()]]
            );
        }

        if ($e instanceof DuplicateTransactionException) {
            return $this->errorResponse(
                $e->getMessage(),
                409,
                $e->isConflictingRequest()
                    ? ['transaction_id' => [$e->getMessage()]]
                    : [],
                ['transaction_id' => $e->getTransactionId()]
            );
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->errorResponse('Resource not found.', 404);
        }

        if ($e instanceof HttpExceptionInterface) {
            return $this->errorResponse(
                $e->getMessage() ?: $fallbackMessage,
                $e->getStatusCode()
            );
        }

        report($e);

        return $this->errorResponse($fallbackMessage, 500);
    }
}
