<?php

namespace App\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    protected string $accountId;
    protected float $currentBalance;
    protected float $requestedAmount;

    public function __construct(string $accountId, float $currentBalance, float $requestedAmount)
    {
        $this->accountId = $accountId;
        $this->currentBalance = $currentBalance;
        $this->requestedAmount = $requestedAmount;

        $message = sprintf(
            "Insufficient funds in account '%s'. Available: %.2f, Requested: %.2f",
            $accountId,
            $currentBalance,
            $requestedAmount
        );

        parent::__construct($message, 422);
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getCurrentBalance(): float
    {
        return $this->currentBalance;
    }

    public function getRequestedAmount(): float
    {
        return $this->requestedAmount;
    }
}
