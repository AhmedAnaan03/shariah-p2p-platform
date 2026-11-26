<?php

namespace Tests\Unit;

use App\Support\InstalmentSplit;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PB-34: each investor's share of an instalment or planned capital return, in proportion to
 * their pledge, always adding up exactly to the amount (largest remainder).
 */
class InstalmentSplitTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: array<int, int>, 2: array<int, int>}>
     */
    public static function splits(): array
    {
        return [
            'blank: nothing to split' => [0, [1 => 500_00, 2 => 500_00], [1 => 0, 2 => 0]],
            'one investor gets it all' => [4_500_00, [7 => 10_000_00], [7 => 4_500_00]],
            'in proportion to the pledges' => [1_000_00, [5 => 3_000_00, 9 => 7_000_00], [5 => 300_00, 9 => 700_00]],
            'the leftover cent goes to the lowest id on a tie' => [100_00, [1 => 1, 2 => 1, 3 => 1], [1 => 33_34, 2 => 33_33, 3 => 33_33]],
            'leftover cents go to the biggest rounding losses' => [10, [1 => 1, 2 => 2, 3 => 3], [1 => 2, 2 => 3, 3 => 5]],
        ];
    }

    /**
     * @param  array<int, int>  $weights
     * @param  array<int, int>  $expected
     */
    #[DataProvider('splits')]
    public function test_shares_are_proportional_and_add_up_exactly(int $cents, array $weights, array $expected): void
    {
        $shares = InstalmentSplit::shares($cents, $weights);

        $this->assertSame($expected, $shares);
        $this->assertSame($cents, array_sum($shares));
    }

    /**
     * @return array<string, array{0: array<int, int>}>
     */
    public static function invalidWeights(): array
    {
        return [
            'no investors' => [[]],
            'pledges of zero' => [[1 => 0, 2 => 0]],
            'a negative pledge' => [[1 => 500, 2 => -100]],
        ];
    }

    /**
     * @param  array<int, int>  $weights
     */
    #[DataProvider('invalidWeights')]
    public function test_invalid_pledges_are_refused(array $weights): void
    {
        $this->expectException(InvalidArgumentException::class);
        InstalmentSplit::shares(1_000, $weights);
    }
}
