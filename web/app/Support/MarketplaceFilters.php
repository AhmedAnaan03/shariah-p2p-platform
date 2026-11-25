<?php

namespace App\Support;

use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\RiskScore;
use Illuminate\Database\Eloquent\Builder;

/**
 * The marketplace's filters (PB-16), on top of the shared ListFilters bar (PB-54).
 *
 * Every filter but the amount is a dropdown with a fixed list of values, so ListFilters can
 * ignore anything else typed into the URL. Tenure is preset ranges, which include their upper
 * bound and exclude their lower one, so a boundary value belongs to exactly one range.
 *
 * The amount is a two-handle slider (amount_min, amount_max), both inclusive. The server takes
 * any whole number, clamps it to MVR 10,000 – 2,000,000 (the request limits) and puts a reversed
 * pair the right way round; the slider itself moves along AMOUNT_STEPS.
 *
 * While the investor has Shariah-only on (PB-31), the contract filter only offers Murabahah
 * and Mudarabah. The rows themselves are restricted by Campaign::scopeVisibleTo, not here.
 */
final class MarketplaceFilters
{
    /** Cards per page: a 3 × 3 grid on desktop. */
    public const PER_PAGE = 9;

    /** The sector filter's option for individual borrowers, who have no business sector. */
    public const INDIVIDUAL = 'individual';

    /** The contract filter's option for either Shariah contract. */
    public const ANY_SHARIAH = 'shariah';

    /** The risk grade filter's option for campaigns without a score yet (until PB-25). */
    public const UNSCORED = 'unscored';

    public const AMOUNT_MIN = 'amount_min';

    public const AMOUNT_MAX = 'amount_max';

    /**
     * The amounts the slider stops at, MVR 10,000 to 2,000,000. Roughly even steps on a
     * logarithmic scale (1, 1.5, 2, 2.5, 3, 4, 5, 6, 7.5 per power of ten), so the small amounts
     * most requests are for get as much of the track as the large ones.
     *
     * @var list<int>
     */
    public const AMOUNT_STEPS = [
        10_000, 15_000, 20_000, 25_000, 30_000, 40_000, 50_000, 60_000, 75_000,
        100_000, 150_000, 200_000, 250_000, 300_000, 400_000, 500_000, 600_000, 750_000,
        1_000_000, 1_250_000, 1_500_000, 1_750_000, 2_000_000,
    ];

    /**
     * key => [label, above (exclusive), up to (inclusive)], in months.
     *
     * @var array<string, array{0: string, 1: ?int, 2: ?int}>
     */
    public const TENURES = [
        'up_to_12' => ['Up to 12 months', null, 12],
        '13_to_36' => ['13 to 36 months', 12, 36],
        'over_36' => ['Over 36 months', 36, null],
    ];

    /**
     * The contract filter's options for this investor.
     *
     * @return array<string, string>
     */
    public static function contracts(bool $shariahOnly): array
    {
        $shariah = [
            Campaign::CONTRACT_MURABAHAH => CampaignPresenter::CONTRACT_LABELS[Campaign::CONTRACT_MURABAHAH],
            Campaign::CONTRACT_MUDARABAH => CampaignPresenter::CONTRACT_LABELS[Campaign::CONTRACT_MUDARABAH],
        ];

        return $shariahOnly ? $shariah : [
            Campaign::CONTRACT_CONVENTIONAL => CampaignPresenter::CONTRACT_LABELS[Campaign::CONTRACT_CONVENTIONAL],
            ...$shariah,
            self::ANY_SHARIAH => 'Any Shariah contract',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sectors(): array
    {
        return [...BorrowerProfile::SECTORS, self::INDIVIDUAL => 'Individual borrower'];
    }

    /**
     * @return array<string, string>
     */
    public static function grades(): array
    {
        $grades = array_combine(RiskScore::GRADES, array_map(fn (string $grade) => "Grade {$grade}", RiskScore::GRADES));

        return [...$grades, self::UNSCORED => 'Not yet scored'];
    }

    /**
     * filter name => its labels, in the order the bar shows them.
     *
     * @return array<string, array<string, string>>
     */
    public static function labels(bool $shariahOnly): array
    {
        return [
            'sector' => self::sectors(),
            'purpose' => Campaign::PURPOSES,
            'tenure' => array_map(fn (array $range) => $range[0], self::TENURES),
            'contract' => self::contracts($shariahOnly),
            'grade' => self::grades(),
        ];
    }

    /**
     * The allowed values for ListFilters::from().
     *
     * @return array<string, list<string>>
     */
    public static function allowed(bool $shariahOnly): array
    {
        return array_map(fn (array $labels) => array_map('strval', array_keys($labels)), self::labels($shariahOnly));
    }

    /**
     * The number filters for ListFilters::from(): both ends of the amount slider.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function numbers(): array
    {
        $bounds = [Campaign::MIN_AMOUNT, Campaign::MAX_AMOUNT];

        return [self::AMOUNT_MIN => $bounds, self::AMOUNT_MAX => $bounds];
    }

    /**
     * Tidy the amount range from the URL: a reversed pair is swapped, and an end at its limit
     * (MVR 10,000 or 2,000,000) is dropped, as it filters nothing.
     */
    public static function normalise(ListFilters $filters): ListFilters
    {
        $min = $filters->number(self::AMOUNT_MIN);
        $max = $filters->number(self::AMOUNT_MAX);
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return $filters->withNumbers([
            self::AMOUNT_MIN => $min === Campaign::MIN_AMOUNT ? null : $min,
            self::AMOUNT_MAX => $max === Campaign::MAX_AMOUNT ? null : $max,
        ]);
    }

    /**
     * What the amount slider needs on the page.
     *
     * @return array{min: int, max: int, steps: list<int>}
     */
    public static function amountSlider(): array
    {
        return ['min' => Campaign::MIN_AMOUNT, 'max' => Campaign::MAX_AMOUNT, 'steps' => self::AMOUNT_STEPS];
    }

    /**
     * The dropdown options for the page.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function options(bool $shariahOnly): array
    {
        return array_map(fn (array $labels) => ListFilters::options($labels), self::labels($shariahOnly));
    }

    /**
     * Narrow the query by every filter that is set. The search is applied separately.
     *
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public static function apply(Builder $query, ListFilters $filters): Builder
    {
        return $query
            ->when($filters->get('sector'), fn (Builder $q, string $sector) => $sector === self::INDIVIDUAL
                ? $q->whereHas('borrower.borrowerProfile', fn ($p) => $p->where('borrower_type', 'individual'))
                : $q->whereHas('borrower.borrowerProfile', fn ($p) => $p->where('sector', $sector)))
            ->when($filters->get('purpose'), fn (Builder $q, string $purpose) => $q->where('purpose', $purpose))
            ->when($filters->number(self::AMOUNT_MIN), fn (Builder $q, int $min) => $q->where('amount_requested', '>=', $min))
            ->when($filters->number(self::AMOUNT_MAX), fn (Builder $q, int $max) => $q->where('amount_requested', '<=', $max))
            ->when($filters->get('tenure'), fn (Builder $q, string $key) => self::inRange($q, 'tenure_months', self::TENURES[$key]))
            ->when($filters->get('contract'), fn (Builder $q, string $contract) => $contract === self::ANY_SHARIAH
                ? $q->whereIn('contract_type', Campaign::SHARIAH_CONTRACTS)
                : $q->where('contract_type', $contract))
            ->when($filters->get('grade'), fn (Builder $q, string $grade) => $grade === self::UNSCORED
                ? $q->whereDoesntHave('currentRiskScore')
                : $q->whereHas('currentRiskScore', fn ($s) => $s->where('grade', $grade)));
    }

    /**
     * @param  Builder<Campaign>  $query
     * @param  array{0: string, 1: ?int, 2: ?int}  $range
     */
    private static function inRange(Builder $query, string $column, array $range): Builder
    {
        [, $above, $upTo] = $range;

        return $query
            ->when($above !== null, fn (Builder $q) => $q->where($column, '>', $above))
            ->when($upTo !== null, fn (Builder $q) => $q->where($column, '<=', $upTo));
    }
}
