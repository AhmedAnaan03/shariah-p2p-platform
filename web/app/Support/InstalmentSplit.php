<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Splits an amount between investors in proportion to what each pledged (PB-34), so each
 * investor can see their share of every instalment or planned capital return. PB-35 pays
 * repayments out with the same split.
 *
 * Largest remainder: everyone first gets their exact share rounded down to the cent; the cents
 * left over go one each to the investors whose exact shares lost the most in rounding (ties to
 * the lower investor id, so the answer never changes between runs). The shares always add up to
 * exactly the amount.
 *
 * Example: MVR 100.00 between three equal pledges → 33.34, 33.33, 33.33.
 */
final class InstalmentSplit
{
    /**
     * @param  array<int, int>  $weights  investor id => pledged cents
     * @return array<int, int> investor id => share in cents
     */
    public static function shares(int $cents, array $weights): array
    {
        $total = array_sum($weights);
        if ($total <= 0 || min($weights) < 0) {
            throw new InvalidArgumentException('The pledges to split by must add up to more than zero.');
        }

        $shares = $remainders = [];
        foreach ($weights as $id => $weight) {
            // Integer maths: $cents × $weight fits easily in 64 bits for any campaign here.
            $shares[$id] = intdiv($cents * $weight, $total);
            $remainders[$id] = ($cents * $weight) % $total;
        }

        $left = $cents - array_sum($shares);
        $order = array_keys($remainders);
        usort($order, fn (int $a, int $b) => [$remainders[$b], $a] <=> [$remainders[$a], $b]);
        foreach (array_slice($order, 0, $left) as $id) {
            $shares[$id]++;
        }

        return $shares;
    }
}
