<?php

namespace App\Support;

use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\RiskScore;
use Illuminate\Database\Eloquent\Builder;

/**
 * The marketplace's filters (PB-16), on top of the shared ListFilters bar (PB-54).
 *
 * Every filter is a dropdown with a fixed list of values, so ListFilters can ignore anything
 * else typed into the URL. Amount and tenure are preset ranges rather than free min/max boxes:
 * nothing to validate, and a range is one tap on a phone.
 *
 * Ranges include their upper bound and exclude their lower one, so a boundary value belongs to
 * exactly one range: MVR 50,000 is "Up to MVR 50,000", MVR 50,000.01 is the next range.
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

    /**
     * key => [label, above (exclusive), up to (inclusive)], in MVR.
     *
     * @var array<string, array{0: string, 1: ?int, 2: ?int}>
     */
    public const AMOUNTS = [
        'up_to_50k' => ['Up to MVR 50,000', null, 50_000],
        '50k_to_200k' => ['MVR 50,000 to 200,000', 50_000, 200_000],
        '200k_to_500k' => ['MVR 200,000 to 500,000', 200_000, 500_000],
        'over_500k' => ['Over MVR 500,000', 500_000, null],
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
            'amount' => array_map(fn (array $range) => $range[0], self::AMOUNTS),
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
            ->when($filters->get('amount'), fn (Builder $q, string $key) => self::inRange($q, 'amount_requested', self::AMOUNTS[$key]))
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
