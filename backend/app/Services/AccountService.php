<?php

namespace App\Services;

use App\Exceptions\AccountAlreadyExistsException;
use App\Models\Account;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Encapsulates account persistence rules so controllers stay transport-focused.
 */
class AccountService
{
    /**
     * Create a new account using the canonicalized request attributes.
     *
     * The database unique constraint remains the source of truth for duplicates,
     * which keeps concurrent requests safe even if they pass validation at the
     * same time.
     *
     * @param  array{account_id:string,name:?string}  $attributes
     * @return Account
     *
     * @throws AccountAlreadyExistsException
     * @throws QueryException
     */
    public function createAccount(array $attributes): Account
    {
        try {
            return Account::create($attributes);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new AccountAlreadyExistsException($attributes['account_id']);
            }

            throw $e;
        }
    }

    /**
     * Detect duplicate-key failures
     * 
     * @param QueryException $exception
      * @return bool
     */
    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '1555', '2067'], true);
    }
}
