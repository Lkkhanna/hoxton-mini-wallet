<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class AccountAlreadyExistsException extends Exception
{
    protected string $accountId;

    public function __construct(string $accountId)
    {
        $this->accountId = $accountId;

        parent::__construct(
            "Account '{$accountId}' already exists.",
            Response::HTTP_CONFLICT
        );
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }
}
