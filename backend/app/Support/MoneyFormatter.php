<?php

namespace App\Support;

class MoneyFormatter
{
    public const SCALE = 2;

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

        [$wholePart, $fractionPart] = array_pad(explode('.', $unsignedValue, self::SCALE), self::SCALE, '');

        $wholePart = preg_replace('/\D/', '', $wholePart ?? '') ?? '';
        $fractionPart = preg_replace('/\D/', '', $fractionPart ?? '') ?? '';

        $wholePart = ltrim($wholePart, '0');
        $wholePart = $wholePart === '' ? '0' : $wholePart;
        $fractionPart = str_pad(substr($fractionPart, 0, self::SCALE), self::SCALE, '0');

        return ($negative ? '-' : '') . $wholePart . '.' . $fractionPart;
    }

    public static function subtract(mixed $left, mixed $right): string
    {
        return bcsub(
            self::normalizeDecimalString($left),
            self::normalizeDecimalString($right),
            self::SCALE
        );
    }

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
