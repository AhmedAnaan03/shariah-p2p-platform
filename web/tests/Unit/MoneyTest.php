<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PB-18: amounts are handled in whole cents so sums are exact.
 */
class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: ?int}>
     */
    public static function inputs(): array
    {
        return [
            'blank' => ['', null],
            'null' => [null, null],
            'letters' => ['abc', null],
            'negative' => ['-5', null],
            'three decimals' => ['1.234', null],
            'array' => [['5'], null],
            'whole' => ['5000', 500000],
            'with commas' => ['1,234.56', 123456],
            'one decimal' => ['12.5', 1250],
            'integer' => [5000, 500000],
            'float' => [12.34, 1234],
            'zero' => ['0', 0],
        ];
    }

    #[DataProvider('inputs')]
    public function test_to_cents(mixed $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::toCents($input));
    }

    public function test_database_values_may_be_negative(): void
    {
        $this->assertSame(-1250, Money::fromDatabase('-12.50'));
        $this->assertSame(125050, Money::fromDatabase('1250.50'));
        $this->assertSame(0, Money::fromDatabase(null));
    }

    public function test_back_to_strings(): void
    {
        $this->assertSame('12500.05', Money::fromCents(1250005));
        $this->assertSame('-0.50', Money::fromCents(-50));
        $this->assertSame('MVR 1,234,567.89', Money::format(123456789));
    }
}
