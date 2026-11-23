<?php

namespace Tests\Feature\Onboarding;

use App\Models\Atoll;
use App\Models\BorrowerProfile;
use App\Models\InvestorProfile;
use App\Models\Island;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PB-53: the address step. House or building name, road (magu), atoll and island from the
 * seeded list (the island must be in the chosen atoll), and a 5-digit postcode.
 */
class AddressStepTest extends TestCase
{
    use RefreshDatabase;

    private User $borrower;

    protected function setUp(): void
    {
        parent::setUp();

        // At the address step: details saved, no address yet.
        $this->borrower = User::factory()->borrower()->create();
        BorrowerProfile::factory()->individual()->withoutAddress()->for($this->borrower)->create();
    }

    /**
     * A valid address in Hulhumalé. Each test changes only what it is about.
     *
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        $island = Island::where('name', 'Hulhumalé')->sole();

        return array_merge([
            'address_house' => 'H. Sea Breeze',
            'address_road' => 'Nirolhu Magu',
            'atoll_id' => (string) $island->atoll_id,
            'island_id' => (string) $island->id,
            'postcode' => '23000',
        ], $overrides);
    }

    private function save(array $input)
    {
        return $this->actingAs($this->borrower)
            ->from('/borrower/onboarding/address')
            ->put('/borrower/onboarding/address', $input);
    }

    public function test_the_step_offers_every_atoll_with_its_islands(): void
    {
        $this->actingAs($this->borrower)->get('/borrower/onboarding/address')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Address')
                ->has('atolls', Atoll::count())
                ->where('atolls.0.label', 'Haa Alif (HA)')
                ->where('atolls.0.islands', fn ($islands) => count($islands) === Atoll::where('code', 'HA')->sole()->islands()->count())
                ->where('address.island_id', '')
                ->missing('legacyAddress')
            );
    }

    public function test_a_valid_address_is_saved_and_the_wizard_moves_on(): void
    {
        $this->save($this->input(['postcode' => '23 000']))
            ->assertRedirect('/borrower/onboarding/finances')
            ->assertSessionHas('status', 'Your address has been saved.');

        $profile = $this->borrower->fresh()->borrowerProfile;
        $this->assertSame('H. Sea Breeze', $profile->address_house);
        $this->assertSame('Nirolhu Magu', $profile->address_road);
        $this->assertSame('Hulhumalé', $profile->island->name);
        $this->assertSame('Malé City', $profile->island->atoll->name);
        $this->assertSame('23000', $profile->postcode, 'Spaces typed in the postcode are removed.');
        $this->assertTrue($profile->hasCompleteAddress());
        $this->assertSame('H. Sea Breeze, Nirolhu Magu, Hulhumalé, Malé City 23000', $profile->addressLine());
    }

    /**
     * Blank and invalid inputs: [changed input, field with the error, expected message].
     *
     * @return array<string, array{0: array<string, mixed>|\Closure, 1: string, 2: string}>
     */
    public static function invalidInputs(): array
    {
        $island = 'Choose your island from the list for the atoll you picked.';
        $postcode = 'Enter your 5-digit postcode, like 20026.';

        return [
            'blank house name' => [['address_house' => ''], 'address_house', 'Enter your house or building name.'],
            'house name over 100 characters' => [['address_house' => str_repeat('a', 101)], 'address_house', 'The house or building name must be 100 characters or fewer.'],
            'blank road' => [['address_road' => ''], 'address_road', 'Enter the road (magu) your home is on.'],
            'road over 100 characters' => [['address_road' => str_repeat('b', 101)], 'address_road', 'The road name must be 100 characters or fewer.'],
            'blank atoll' => [['atoll_id' => ''], 'atoll_id', 'Choose your atoll.'],
            'atoll that does not exist' => [['atoll_id' => '99999'], 'atoll_id', 'Choose your atoll from the list.'],
            'atoll as text' => [['atoll_id' => 'Kaafu'], 'atoll_id', 'Choose your atoll from the list.'],
            'blank island' => [['island_id' => ''], 'island_id', 'Choose your island.'],
            'island that does not exist' => [['island_id' => '99999'], 'island_id', $island],
            'island as text' => [['island_id' => 'Hulhumalé'], 'island_id', $island],
            'blank postcode' => [['postcode' => ''], 'postcode', $postcode],
            'postcode with 4 digits' => [['postcode' => '2000'], 'postcode', $postcode],
            'postcode with 6 digits' => [['postcode' => '200260'], 'postcode', $postcode],
            'postcode with letters' => [['postcode' => '2O026'], 'postcode', $postcode],
            'postcode with a dash' => [['postcode' => '20-026'], 'postcode', $postcode],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_a_clear_message(array $change, string $field, string $message): void
    {
        $this->save($this->input($change))
            ->assertRedirect('/borrower/onboarding/address')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertNull($this->borrower->fresh()->borrowerProfile->island_id, 'Nothing is saved when validation fails.');
    }

    public function test_an_island_from_another_atoll_is_refused(): void
    {
        // Thinadhoo exists in Vaavu and in Gaafu Dhaalu: pick Vaavu's with the Gaafu Dhaalu atoll.
        $vaavuThinadhoo = Island::where('name', 'Thinadhoo')->whereHas('atoll', fn ($q) => $q->where('code', 'V'))->sole();
        $gaafuDhaalu = Atoll::where('code', 'GDh')->sole();

        $this->save($this->input(['atoll_id' => (string) $gaafuDhaalu->id, 'island_id' => (string) $vaavuThinadhoo->id]))
            ->assertSessionHasErrors(['island_id' => 'Choose your island from the list for the atoll you picked.']);
    }

    /**
     * Valid boundary values.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function boundaryValidInputs(): array
    {
        return [
            'house name of exactly 100 characters' => [['address_house' => str_repeat('a', 100)]],
            'postcode starting with 0' => [['postcode' => '01234']],
            'postcode written with a space' => [['postcode' => '230 00']],
        ];
    }

    #[DataProvider('boundaryValidInputs')]
    public function test_boundary_values_are_accepted(array $change): void
    {
        $this->save($this->input($change))->assertSessionHasNoErrors();
    }

    public function test_investors_use_the_same_address_step(): void
    {
        $investor = User::factory()->investor()->create();
        InvestorProfile::factory()->withoutAddress()->for($investor)->create();

        $this->actingAs($investor)->put('/investor/onboarding/address', $this->input())
            ->assertRedirect('/investor/onboarding/documents');

        $this->assertSame('Hulhumalé', $investor->fresh()->investorProfile->island->name);
    }

    public function test_the_database_refuses_a_postcode_that_is_not_5_digits(): void
    {
        $this->expectException(QueryException::class);

        BorrowerProfile::where('user_id', $this->borrower->id)->update(['postcode' => '12AB5']);
    }
}
