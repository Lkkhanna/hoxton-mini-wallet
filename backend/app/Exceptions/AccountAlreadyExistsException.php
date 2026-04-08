<?php

namespace App\Exceptions;

use Exception;

class AccountAlreadyExistsException extends Exception
{
    protected string $accountId;

    public function __construct(string $accountId)
    {
        $this->accountId = $accountId;

        parent::__construct(
            "Account '{$accountId}' already exists.",
            409
        );
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }
}
