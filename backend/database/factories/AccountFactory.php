<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Lightweight account factory used for feature tests and future seed helpers.
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'account_id' => strtoupper($this->faker->bothify('ACC###')),
            'name' => $this->faker->name(),
        ];
    }

    public function withAccountId(string $accountId): self
    {
        return $this->state(fn (): array => [
            'account_id' => strtoupper(trim($accountId)),
        ]);
    }
}
