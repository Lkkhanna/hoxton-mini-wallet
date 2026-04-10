<?php

namespace App\Helpers;

/**
 * Small money-value helper for normalizing decimal strings before arithmetic.
 *
 * The application stores and compares money as fixed-scale strings to avoid
 * floating-point precision issues in balances and transfers.
 */
class MoneyFormatter
{
    /**
     * Number of decimal places used across all money operations.
     */
    public const SCALE = 2;

    /**
     * Normalize user, database, or computed input into a signed decimal string.
     *
     * Examples:
     * - 12         => 12.00
     * - "001.5"    => 1.50
     * - "-7.899"   => -7.89
     * - null / ""  => 0.00
     */
    public static function normalizeDecimalString(mixed $value): string
    {
        $stringValue = trim((string) ($value ?? '0'));

        if ($stringValue === '') {
            return '0.00';
        }

        $negative = str_starts_with($stringValue, '-');
        $unsignedValue = ltrim($stringValue, '+-');

        if ($unsignedValue === '') {
            return '0.00';
        }

        // Split the numeric string into whole/fractional parts, then sanitize
        // each side independently so unexpected characters are discarded.
        [$wholePart, $fractionPart] = array_pad(explode('.', $unsignedValue, self::SCALE), self::SCALE, '');

        $wholePart = preg_replace('/\D/', '', $wholePart ?? '') ?? '';
        $fractionPart = preg_replace('/\D/', '', $fractionPart ?? '') ?? '';

        $wholePart = ltrim($wholePart, '0');
        $wholePart = $wholePart === '' ? '0' : $wholePart;
        $fractionPart = str_pad(substr($fractionPart, 0, self::SCALE), self::SCALE, '0');

        return ($negative ? '-' : '') . $wholePart . '.' . $fractionPart;
    }

    /**
     * Subtract two money values and return the result as a normalized string.
     */
    public static function subtract(mixed $left, mixed $right): string
    {
        return bcsub(
            self::normalizeDecimalString($left),
            self::normalizeDecimalString($right),
            self::SCALE
        );
    }

    /**
     * Return credits minus debits while preserving the correct sign.
     *
     * This is mainly used when deriving an account balance from ledger sums.
     */
    public static function signedDifference(mixed $credits, mixed $debits): string
    {
        $normalizedCredits = self::normalizeDecimalString($credits);
        $normalizedDebits = self::normalizeDecimalString($debits);
        $comparison = bccomp($normalizedCredits, $normalizedDebits, self::SCALE);

        if ($comparison === 0) {
            return '0.00';
        }

        if ($comparison > 0) {
            return bcsub($normalizedCredits, $normalizedDebits, self::SCALE);
        }

        return '-' . bcsub($normalizedDebits, $normalizedCredits, self::SCALE);
    }
}
