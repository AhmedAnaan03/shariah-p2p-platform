<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Splits an old single-line address ("M. Blue Lagoon, Malé") into the structured fields
 * (PB-53). Used once, by the migration that moved addresses into the new columns.
 *
 * It is a best guess, never trusted on its own: the migration keeps the original text in
 * address_legacy and flags the profile, so the user checks every field before the address
 * counts as complete. Anything it can't place is left empty for the user to fill in.
 *
 *   LegacyAddress::parse('M. Blue Lagoon, Majeedhee Magu, Malé 20026', $islands)
 *   → ['house' => 'M. Blue Lagoon', 'road' => 'Majeedhee Magu', 'island_id' => 189, 'postcode' => '20026']
 */
final class LegacyAddress
{
    /**
     * @param  list<array{id: int, name: string, atoll_name: string, atoll_code: string}>  $islands
     * @return array{house: ?string, road: ?string, island_id: ?int, postcode: ?string}
     */
    public static function parse(?string $text, array $islands): array
    {
        $result = ['house' => null, 'road' => null, 'island_id' => null, 'postcode' => null];
        $text = trim((string) $text);

        if ($text === '') {
            return $result;
        }

        // A Maldivian postcode is 5 digits; take the first one found, wherever it is.
        if (preg_match('/(?<!\d)(\d{5})(?!\d)/', $text, $match)) {
            $result['postcode'] = $match[1];
            $text = trim(str_replace($match[1], '', $text));
        }

        $parts = array_values(array_filter(
            array_map('trim', preg_split('/[,\n]+/', $text)),
            fn (string $part) => $part !== '',
        ));

        // The island is usually last, so look from the end. A part that names an island is
        // taken out even when the name is used in several atolls and can't be settled: it is
        // not a road, and the user picks the island from the list.
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            if (self::namesAnIsland($parts[$i], $islands)) {
                $result['island_id'] = self::matchIsland($parts[$i], $parts, $islands);
                array_splice($parts, $i, 1);
                break;
            }
        }

        // What is left: the house or building name first, then the road and anything else.
        $result['house'] = isset($parts[0]) ? mb_substr($parts[0], 0, 100) : null;
        $rest = array_slice($parts, 1);
        $result['road'] = $rest === [] ? null : mb_substr(implode(', ', $rest), 0, 100);

        return $result;
    }

    /**
     * @param  list<array{id: int, name: string, atoll_name: string, atoll_code: string}>  $islands
     */
    private static function namesAnIsland(string $part, array $islands): bool
    {
        $wanted = self::normalise($part);

        return $wanted !== '' && array_any($islands, fn (array $island) => self::normalise($island['name']) === $wanted);
    }

    /**
     * The id of the one island this part names, or null. A name used in several atolls
     * ("Meedhoo") only counts if another part of the address names the atoll.
     *
     * @param  list<string>  $allParts
     * @param  list<array{id: int, name: string, atoll_name: string, atoll_code: string}>  $islands
     */
    private static function matchIsland(string $part, array $allParts, array $islands): ?int
    {
        $wanted = self::normalise($part);
        if ($wanted === '') {
            return null;
        }

        $candidates = array_values(array_filter($islands, fn (array $island) => self::normalise($island['name']) === $wanted));

        if (count($candidates) === 1) {
            return $candidates[0]['id'];
        }

        $context = self::normalise(implode(' ', $allParts));
        $inAtoll = array_values(array_filter(
            $candidates,
            fn (array $island) => str_contains($context, self::normalise($island['atoll_name'])),
        ));

        return count($inAtoll) === 1 ? $inAtoll[0]['id'] : null;
    }

    /**
     * Lower case, accents removed ("Malé" = "male"), an atoll prefix such as "K." or "HA."
     * dropped, and only letters kept.
     */
    private static function normalise(string $value): string
    {
        $value = strtolower(Str::ascii($value));
        $value = preg_replace('/^[a-z]{1,3}\.\s*/', '', trim($value));

        return preg_replace('/[^a-z]/', '', $value);
    }
}
