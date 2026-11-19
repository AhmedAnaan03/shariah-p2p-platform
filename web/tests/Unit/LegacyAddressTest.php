<?php

namespace Tests\Unit;

use App\Support\LegacyAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PB-53: the best guess that splits an old single-line address into the new fields during the
 * data migration. A plain unit test: the island list is passed in, so no database is needed.
 */
class LegacyAddressTest extends TestCase
{
    private const ISLANDS = [
        ['id' => 1, 'name' => 'Malé', 'atoll_name' => 'Malé City', 'atoll_code' => 'Malé'],
        ['id' => 2, 'name' => 'Hulhumalé', 'atoll_name' => 'Malé City', 'atoll_code' => 'Malé'],
        ['id' => 3, 'name' => 'Thinadhoo', 'atoll_name' => 'Vaavu', 'atoll_code' => 'V'],
        ['id' => 4, 'name' => 'Thinadhoo', 'atoll_name' => 'Gaafu Dhaalu', 'atoll_code' => 'GDh'],
        ['id' => 5, 'name' => 'Hithadhoo', 'atoll_name' => 'Laamu', 'atoll_code' => 'L'],
    ];

    /**
     * [old address, expected house, road, island id, postcode]
     *
     * @return array<string, array{0: ?string, 1: ?string, 2: ?string, 3: ?int, 4: ?string}>
     */
    public static function addresses(): array
    {
        return [
            'blank' => ['', null, null, null, null],
            'null' => [null, null, null, null, null],
            'house and island' => ['M. Blue Lagoon, Malé', 'M. Blue Lagoon', null, 1, null],
            'house, road, island and postcode' => ['H. Blue Lagoon, Majeedhee Magu, Malé 20026', 'H. Blue Lagoon', 'Majeedhee Magu', 1, '20026'],
            'island written without the accent' => ['Hiyaa Flat H8, Hulhumale', 'Hiyaa Flat H8', null, 2, null],
            'island written with an apostrophe' => ["Hulhumale'", null, null, 2, null],
            'island first' => ['Hulhumalé, Hiyaa Flat H8', 'Hiyaa Flat H8', null, 2, null],
            'island name used in two atolls' => ['Fehivina, Thinadhoo', 'Fehivina', null, null, null],
            'atoll named, so the island is clear' => ['Fehivina, Thinadhoo, Gaafu Dhaalu', 'Fehivina', 'Gaafu Dhaalu', 4, null],
            'atoll prefix before the island' => ['Sea View, L. Hithadhoo', 'Sea View', null, 5, null],
            'on separate lines' => ["Rose Villa\nOrchid Magu\nMalé", 'Rose Villa', 'Orchid Magu', 1, null],
            'unreadable text' => ['qwertyuiop', 'qwertyuiop', null, null, null],
            'only a postcode' => ['20026', null, null, null, '20026'],
            '6-digit number is not a postcode' => ['Flat 200260, Malé', 'Flat 200260', null, 1, null],
            'house name cut to 100 characters' => [str_repeat('a', 120).', Malé', str_repeat('a', 100), null, 1, null],
        ];
    }

    #[DataProvider('addresses')]
    public function test_an_old_address_is_split_into_the_new_fields(?string $text, ?string $house, ?string $road, ?int $island, ?string $postcode): void
    {
        $this->assertSame(
            ['house' => $house, 'road' => $road, 'island_id' => $island, 'postcode' => $postcode],
            LegacyAddress::parse($text, self::ISLANDS),
        );
    }
}
