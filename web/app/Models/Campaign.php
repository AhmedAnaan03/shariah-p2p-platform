<?php

namespace App\Models;

use App\Exceptions\ContractRuleViolation;
use App\Support\Money;
use Database\Factories\CampaignFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Status, review and funding fields are changed by the workflow code, not by the borrower's form.
#[Fillable([
    'title', 'purpose', 'description', 'business_background', 'amount_requested', 'tenure_months',
    'contract_type', 'interest_rate_percent', 'markup_amount', 'asset_description',
    'investor_profit_share_percent',
])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    public const CONTRACT_CONVENTIONAL = 'conventional';

    public const CONTRACT_MURABAHAH = 'murabahah';

    public const CONTRACT_MUDARABAH = 'mudarabah';

    public const SHARIAH_CONTRACTS = [self::CONTRACT_MURABAHAH, self::CONTRACT_MUDARABAH];

    /**
     * The purposes a borrower can choose (PB-13 acceptance criterion), stored as the key.
     */
    public const PURPOSES = [
        'start_up' => 'Start-up',
        'growth' => 'Growth',
        'education' => 'Education',
        'personal' => 'Personal',
    ];

    /**
     * Limits on a request (PB-13). In one place so the form request, the page hints and the
     * tests all use the same numbers.
     *
     * Tenures: LendingClub's loans, which the risk model learns from, are only 36 or 60
     * months, so the other choices are outside the model's training range. See RiskFeatures.
     */
    public const MIN_AMOUNT = 10_000;

    public const MAX_AMOUNT = 2_000_000;

    public const TENURES = [3, 6, 12, 18, 24, 36, 48, 60];

    public const MAX_INTEREST_RATE = 30;

    public const MIN_PROFIT_SHARE = 1;

    public const MAX_PROFIT_SHARE = 99;

    /**
     * How long an approved campaign stays open for pledges (PB-15). PB-20's scheduled job
     * settles or refunds it when the deadline passes.
     */
    public const FUNDING_DAYS = 30;

    /**
     * Deadlines end at midnight in the Maldives, not in the server's UTC: a deadline of
     * "18 Dec" means the end of 18 Dec in Malé (18:59:59 UTC).
     */
    public const LOCAL_TIMEZONE = 'Indian/Maldives';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_LIVE = 'live';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    /** Target reached (PB-20); the money waits in escrow until it is paid out (PB-34). */
    public const STATUS_FUNDED = 'funded';

    /** Deadline passed short of the target (PB-20); every pledge was refunded. */
    public const STATUS_FAILED = 'failed';

    /**
     * Paid out to the borrower, with a schedule (PB-34). For a Mudarabah the schedule is planned
     * capital returns, not a debt; pages show it as "Returning capital".
     */
    public const STATUS_REPAYING = 'repaying';

    /**
     * Finished (PB-36, PB-29): every instalment paid, or for a Mudarabah all capital settled
     * (returned, or reduced by a genuine loss). Pages show a Mudarabah as "Capital settled",
     * never "repaid".
     */
    public const STATUS_COMPLETED = 'completed';

    /**
     * The terms that fix what a funded contract is worth (PB-28): the asset cost (or amount),
     * the tenure, the contract type and its return term. Once a campaign is funded, investors
     * have agreed to them, so none can change: a Murabahah's markup is disclosed and fixed.
     */
    public const LOCKED_AFTER_FUNDING = [
        'amount_requested', 'tenure_months', 'contract_type', 'interest_rate_percent',
        'markup_amount', 'asset_description', 'investor_profit_share_percent',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'tenure_months' => 'integer',
            'interest_rate_percent' => 'decimal:2',
            'markup_amount' => 'decimal:2',
            'investor_profit_share_percent' => 'decimal:2',
            'funding_deadline' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'funded_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Model events: "updating" runs before every update made through Eloquent.
     */
    protected static function booted(): void
    {
        static::updating(function (Campaign $campaign) {
            $campaign->ensureTermsUnchangedAfterFunding();
        });
    }

    /**
     * PB-28: once funded, the contract's terms are agreed and can't change. For a Murabahah that
     * means the markup (and the asset cost it is added to) is fixed for good, so the sale price
     * disclosed to investors and the borrower is always the one repaid. It reads funded_at as it
     * was stored before this save (getOriginal), so the save that marks the campaign funded
     * still goes through; every save after it can't touch the terms.
     *
     * @throws ContractRuleViolation
     */
    public function ensureTermsUnchangedAfterFunding(): void
    {
        if ($this->getOriginal('funded_at') === null) {
            return;
        }

        $changed = array_values(array_filter(self::LOCKED_AFTER_FUNDING, fn (string $term) => $this->isDirty($term)));
        if ($changed !== []) {
            throw new ContractRuleViolation(
                'A funded campaign\'s agreed terms cannot change ('.implode(', ', $changed).'). '
                .'A Murabahah markup is fixed once the contract is agreed.'
            );
        }
    }

    public function isMudarabah(): bool
    {
        return $this->contract_type === self::CONTRACT_MUDARABAH;
    }

    /**
     * The funding deadline for a campaign approved at $approvedAt: the end of the 30th day
     * after, Maldives time, stored in UTC like every other time.
     */
    public static function fundingDeadlineFrom(DateTimeInterface $approvedAt): Carbon
    {
        return Carbon::instance($approvedAt)->setTimezone(self::LOCAL_TIMEZONE)
            ->addDays(self::FUNDING_DAYS)->endOfDay()->utc();
    }

    /**
     * "18 Dec 2026": the deadline's date in the Maldives, for emails and messages.
     */
    public function fundingDeadlineLabel(): ?string
    {
        return $this->funding_deadline ? self::localDate($this->funding_deadline) : null;
    }

    /**
     * Today's date in the Maldives ("2026-11-27"): what due dates and planned dates are compared
     * with (PB-35, PB-29).
     */
    public static function today(): string
    {
        return now()->setTimezone(self::LOCAL_TIMEZONE)->toDateString();
    }

    /**
     * "18 Dec 2026" for a moment, as a date in the Maldives.
     */
    public static function localDate(DateTimeInterface $moment): string
    {
        return Carbon::instance($moment)->setTimezone(self::LOCAL_TIMEZONE)->format('j M Y');
    }

    /**
     * Shariah mode is not stored separately; it follows from the contract type.
     */
    public function isShariah(): bool
    {
        return self::isShariahContract($this->contract_type);
    }

    public static function isShariahContract(?string $contractType): bool
    {
        return in_array($contractType, self::SHARIAH_CONTRACTS, true);
    }

    /**
     * Live campaigns whose funding deadline has not passed: what the marketplace lists
     * (PB-15; PB-16 adds search, filters and pages on top of this).
     *
     * @param  Builder<Campaign>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('status', self::STATUS_LIVE)->where('funding_deadline', '>', now());
    }

    /**
     * The open campaigns an investor may browse and pledge to (PB-31): listed, and only
     * Murabahah or Mudarabah while the investor has Shariah-only on. Both the marketplace and
     * the campaign page go through this, so hiding is enforced on the server, not by the cards.
     *
     * @param  Builder<Campaign>  $query
     */
    public function scopeVisibleTo(Builder $query, User $investor): void
    {
        $query->listed()->when(
            $investor->investorProfile?->shariah_only,
            fn (Builder $q) => $q->whereIn('contract_type', self::SHARIAH_CONTRACTS),
        );
    }

    /**
     * Whether this campaign is open in the marketplace right now (the listed scope, for one model).
     */
    public function isListed(): bool
    {
        return $this->status === self::STATUS_LIVE && $this->funding_deadline?->isFuture();
    }

    /**
     * Adds `raised_amount` (money pledged and not refunded) and `investors_count` (different
     * investors) to each campaign (PB-16, PB-17). Both are 0 until pledging arrives (PB-19).
     *
     * @param  Builder<Campaign>  $query
     */
    public function scopeWithFunding(Builder $query): void
    {
        $query->withSum(['pledges as raised_amount' => fn ($q) => $q->whereIn('status', Pledge::COUNTED_STATUSES)], 'amount')
            ->withCount(['pledges as investors_count' => fn ($q) => $q->whereIn('status', Pledge::COUNTED_STATUSES)
                ->select(DB::raw('count(distinct investor_id)'))]);
    }

    /**
     * The Murabahah markup as a percentage of the asset cost, for the whole tenure (not a yearly
     * rate): a MVR 4,000 markup on a MVR 50,000 asset is 8.0%.
     */
    public function markupPercent(): ?float
    {
        if ($this->contract_type !== self::CONTRACT_MURABAHAH || (float) $this->amount_requested <= 0) {
            return null;
        }

        return round((float) $this->markup_amount / (float) $this->amount_requested * 100, 1);
    }

    /**
     * The return figure a campaign card shows under its contract's label (Interest, Markup,
     * Profit share), and the unit on the line below it, so a Murabahah markup can never be read
     * as a yearly rate: ["8.5%", "a year"], ["8.0%", "in total"], ["60 : 40", "investors : business"].
     *
     * @return array{0: string, 1: string}
     */
    public function rateParts(): array
    {
        return match ($this->contract_type) {
            self::CONTRACT_CONVENTIONAL => [self::percent($this->interest_rate_percent), 'a year'],
            self::CONTRACT_MURABAHAH => [self::percent($this->markupPercent()), 'in total'],
            self::CONTRACT_MUDARABAH => [self::ratio($this->investor_profit_share_percent), 'investors : business'],
        };
    }

    /**
     * The card's figure and unit as one phrase ("8.0% in total"), for messages and tests.
     */
    public function rateForCard(): string
    {
        return implode(' ', $this->rateParts());
    }

    /**
     * What an investor can expect to earn, for the campaign page (PB-17). Shariah contracts
     * never say "interest" or "guaranteed": a Murabahah shows its fixed markup over the whole
     * tenure (never a yearly rate) and a Mudarabah shows only its profit-share ratio, because its
     * return depends on profit that hasn't been made yet.
     *
     * @return array{label: string, value: string, note: string}
     */
    public function projectedReturn(): array
    {
        $months = "{$this->tenure_months} months";

        return match ($this->contract_type) {
            self::CONTRACT_CONVENTIONAL => [
                'label' => 'Interest rate',
                'value' => self::percent($this->interest_rate_percent).' a year',
                'note' => "A fixed yearly rate, repaid with the capital in instalments over {$months}. You could lose some or all of your money if the borrower doesn't repay.",
            ],
            self::CONTRACT_MURABAHAH => [
                'label' => 'Expected markup',
                'value' => self::percent($this->markupPercent())." markup over {$months}",
                'note' => 'A fixed markup of '.self::money($this->markup_amount)." on the asset cost, agreed now for the whole {$months}. It is a total, not a yearly rate. Your returns could be lower than expected, or you could receive nothing, if the business doesn't pay.",
            ],
            self::CONTRACT_MUDARABAH => [
                'label' => 'Expected profit share',
                'value' => self::percent($this->investor_profit_share_percent)." of the business's actual profit",
                'note' => 'Investors and the business share the actual profit '.self::ratio($this->investor_profit_share_percent).", over {$months}. Returns depend on that profit: they could be lower than expected, or you could receive nothing.",
            ],
        };
    }

    /**
     * "60 : 40": the investors' share of the profit, then the business's.
     */
    private static function ratio(mixed $investorShare): string
    {
        $investors = (float) $investorShare;

        return self::trimmed($investors).' : '.self::trimmed(100 - $investors);
    }

    private static function trimmed(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }

    /**
     * What a campaign's contract terms are called, for pages and emails. Shariah contracts
     * never use the word "interest" (CLAUDE.md, design system "Voice and content").
     *
     * @return list<array{label: string, value: string, mono: bool}>
     */
    public function termsForDisplay(): array
    {
        return match ($this->contract_type) {
            self::CONTRACT_CONVENTIONAL => [
                ['label' => 'Interest rate', 'value' => self::percent($this->interest_rate_percent).' a year', 'mono' => true],
            ],
            self::CONTRACT_MURABAHAH => [
                ['label' => 'Asset being bought', 'value' => $this->asset_description, 'mono' => false],
                ['label' => 'Fixed markup', 'value' => self::money($this->markup_amount), 'mono' => true],
                ['label' => 'Total sale price', 'value' => self::money((float) $this->amount_requested + (float) $this->markup_amount), 'mono' => true],
            ],
            self::CONTRACT_MUDARABAH => [
                ['label' => "Investors' profit share", 'value' => self::percent($this->investor_profit_share_percent).' of the profit', 'mono' => true],
                ['label' => "Business's profit share", 'value' => self::percent(100 - (float) $this->investor_profit_share_percent).' of the profit', 'mono' => true],
            ],
        };
    }

    private static function money(mixed $value): string
    {
        return 'MVR '.number_format((float) $value, 2);
    }

    /**
     * "8.5%": one decimal, as the design system formats percentages.
     */
    private static function percent(mixed $value): string
    {
        return number_format((float) $value, 1).'%';
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    /**
     * The borrower's financial information for this request (PB-14).
     */
    public function financials(): HasOne
    {
        return $this->hasOne(CampaignFinancials::class);
    }

    /**
     * The MMA credit report the borrower attached to this request, if any (PB-14).
     */
    public function creditReport(): HasOne
    {
        return $this->hasOne(KycDocument::class)->where('document_type', KycDocument::TYPE_CREDIT_REPORT);
    }

    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class);
    }

    public function escrowWallet(): HasOne
    {
        return $this->hasOne(WalletAccount::class);
    }

    public function repaymentSchedules(): HasMany
    {
        return $this->hasMany(RepaymentSchedule::class);
    }

    /**
     * A Mudarabah's period reports (PB-29), approved, rejected and waiting.
     */
    public function businessReports(): HasMany
    {
        return $this->hasMany(BusinessReport::class);
    }

    /**
     * Each investor's share of every repayment or approved report (PB-36, PB-29).
     */
    public function investorPayouts(): HasMany
    {
        return $this->hasMany(InvestorPayout::class);
    }

    /**
     * The investor's percentage of a Mudarabah's profit in basis points ("60.00" → 6000), so the
     * profit share is worked out in whole numbers.
     */
    public function investorShareBasisPoints(): int
    {
        return (int) Money::toCents((string) $this->investor_profit_share_percent);
    }

    public function riskScores(): HasMany
    {
        return $this->hasMany(RiskScore::class);
    }

    /**
     * The latest successful risk score, if any (PB-16 filters on its grade; PB-25 creates
     * scores and PB-26 shows the explanation). Pending and failed attempts don't count.
     */
    public function currentRiskScore(): HasOne
    {
        return $this->hasOne(RiskScore::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('status', RiskScore::STATUS_SCORED));
    }
}
