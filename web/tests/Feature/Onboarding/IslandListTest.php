<?php

namespace Tests\Feature\Onboarding;

use App\Models\Atoll;
use App\Models\Island;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PB-53: the seeded list of Maldivian atolls and inhabited islands behind the address
 * dropdowns (source cited in database/data/maldives_islands.php).
 */
class IslandListTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_administrative_atoll_and_male_city_is_listed_north_to_south(): void
    {
        $atolls = Atoll::orderBy('sort_order')->pluck('code')->all();

        $this->assertSame([
            'HA', 'HDh', 'Sh', 'N', 'R', 'B', 'Lh', 'K', 'Malé', 'AA', 'ADh', 'V', 'M', 'F', 'Dh', 'Th', 'L', 'GA', 'GDh', 'Gn', 'S',
        ], $atolls);
    }

    public function test_each_atoll_has_inhabited_islands_and_the_total_matches_the_source(): void
    {
        $this->assertSame(188, Island::count());
        $this->assertSame(0, Atoll::doesntHave('islands')->count());
    }

    public function test_the_capital_and_its_suburbs_are_under_male_city_not_kaafu(): void
    {
        $maleCity = Atoll::where('name', 'Malé City')->sole()->islands->pluck('name')->sort()->values()->all();

        $this->assertSame(['Hulhumalé', 'Malé', 'Villimalé'], $maleCity);
        $this->assertFalse(Atoll::where('code', 'K')->sole()->islands->contains('name', 'Malé'));
    }

    public function test_island_names_can_repeat_across_atolls_but_not_within_one(): void
    {
        $this->assertGreaterThan(1, Island::where('name', 'Meedhoo')->count());
        $this->assertSame(0, Island::query()->selectRaw('atoll_id, name, count(*) c')->groupBy('atoll_id', 'name')->havingRaw('c > 1')->get()->count());
    }
}
