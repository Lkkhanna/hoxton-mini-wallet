<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class InsufficientFundsException extends Exception
{
    protected string $accountId;
    protected string $currentBalance;
    protected string $requestedAmount;

    public function __construct(string $accountId, string $currentBalance, string $requestedAmount)
    {
        $this->accountId = $accountId;
        $this->currentBalance = $currentBalance;
        $this->requestedAmount = $requestedAmount;

        $message = sprintf(
            "Insufficient funds in account '%s'. Available: %s, Requested: %s",
            $accountId,
            $currentBalance,
            $requestedAmount
        );

        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getCurrentBalance(): string
    {
        return $this->currentBalance;
    }

    public function getRequestedAmount(): string
    {
        return $this->requestedAmount;
    }
}
