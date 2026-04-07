<?php

namespace App\Exceptions;

use Exception;

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
            409
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
