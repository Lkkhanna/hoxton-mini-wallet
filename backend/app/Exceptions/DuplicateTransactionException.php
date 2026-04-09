<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class DuplicateTransactionException extends Exception
{
    protected string $transactionId;
    protected bool $conflictingRequest;

    public function __construct(string $transactionId, bool $conflictingRequest = false)
    {
        $this->transactionId = $transactionId;
        $this->conflictingRequest = $conflictingRequest;

        parent::__construct(
            $conflictingRequest
                ? "Transaction '{$transactionId}' has already been used for a different transfer request."
                : "Transaction '{$transactionId}' has already been processed.",
            Response::HTTP_CONFLICT
        );
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function isConflictingRequest(): bool
    {
        return $this->conflictingRequest;
    }
}
