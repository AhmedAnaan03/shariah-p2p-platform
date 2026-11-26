<?php

namespace App\Support;

/**
 * Money arithmetic in whole cents (laari), so sums never pick up floating-point error (PB-18).
 * The database stores DECIMAL(15, 2); amounts are converted to cents on the way in and back to a
 * "12500.00" string on the way out.
 */
final class Money
{
    /**
     * "12,500.5", 12500.5 or "12500.50" → 1250050. Anything not a plain amount → null.
     */
    public static function toCents(mixed $amount): ?int
    {
        if (is_int($amount)) {
            return $amount * 100;
        }
        if (is_float($amount)) {
            return (int) round($amount * 100);
        }
        if (! is_string($amount)) {
            return null;
        }

        $clean = str_replace([',', ' '], '', trim($amount));
        if (! preg_match('/^(\d{1,13})(?:\.(\d{1,2}))?$/', $clean, $parts)) {
            return null;
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '0', 2, '0');
    }

    /**
     * A DECIMAL value read from the database ("12500.50", "-300.00", 0) → cents. Unlike
     * toCents(), negatives are allowed: the external account's balance is below zero.
     */
    public static function fromDatabase(mixed $value): int
    {
        $text = trim((string) ($value ?? '0'));
        $negative = str_starts_with($text, '-');
        $cents = self::toCents(ltrim($text, '-+'));

        return $cents === null ? 0 : ($negative ? -$cents : $cents);
    }

    /**
     * 1250050 → "12500.50", for the database.
     */
    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * 1250050 → "MVR 12,500.50", for messages.
     */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';

        return $sign.'MVR '.number_format(intdiv(abs($cents), 100)).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
