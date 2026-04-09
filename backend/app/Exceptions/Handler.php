<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // Handle insufficient funds
        $this->renderable(function (InsufficientFundsException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => [
                    'amount' => [$e->getMessage()],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        // Handle account creation conflicts
        $this->renderable(function (AccountAlreadyExistsException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [
                    'account_id' => [$e->getMessage()],
                ],
                'data' => [
                    'account_id' => $e->getAccountId(),
                ],
            ], Response::HTTP_CONFLICT);
        });

        // Handle duplicate transactions (idempotency)
        $this->renderable(function (DuplicateTransactionException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->isConflictingRequest()
                    ? [
                        'transaction_id' => [$e->getMessage()],
                    ]
                    : [],
                'data'    => [
                    'transaction_id' => $e->getTransactionId(),
                ],
            ], Response::HTTP_CONFLICT);
        });

        // Handle model not found (invalid account_id etc.)
        $this->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], Response::HTTP_NOT_FOUND);
            }
        });

        // Handle invalid routes for API requests
        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'The requested endpoint could not be found.',
                ], Response::HTTP_NOT_FOUND);
            }
        });

        // Handle unsupported methods cleanly for API requests
        $this->renderable(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested HTTP method is not supported for this endpoint.',
                ], Response::HTTP_METHOD_NOT_ALLOWED);
            }
        });

        // Fallback JSON response for unexpected API failures
        $this->renderable(function (Throwable $e, $request) {
            if (
                ($request->wantsJson() || $request->is('api/*'))
                && !($e instanceof ValidationException)
                && !($e instanceof InsufficientFundsException)
                && !($e instanceof AccountAlreadyExistsException)
                && !($e instanceof DuplicateTransactionException)
                && !($e instanceof ModelNotFoundException)
                && !($e instanceof NotFoundHttpException)
                && !($e instanceof MethodNotAllowedHttpException)
            ) {
                $statusCode = $e instanceof HttpExceptionInterface
                    ? $e->getStatusCode()
                    : Response::HTTP_INTERNAL_SERVER_ERROR;

                return response()->json([
                    'success' => false,
                    'message' => $statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR
                        ? 'An unexpected server error occurred.'
                        : ($e->getMessage() ?: 'The request could not be processed.'),
                ], $statusCode);
            }
        });

        $this->reportable(function (Throwable $e) {
            Log::error('Unhandled exception reported.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        });
    }

    protected function invalidJson($request, ValidationException $exception): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'The given data was invalid.',
            'errors' => $exception->errors(),
        ], $exception->status);
    }
}
