<?php

namespace App\Models;

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
        ];
    }

    /**
     * Shariah mode is not stored separately; it follows from the contract type.
     */
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
     * "18 Dec 2026" for a moment, as a date in the Maldives.
     */
    public static function localDate(DateTimeInterface $moment): string
    {
        return Carbon::instance($moment)->setTimezone(self::LOCAL_TIMEZONE)->format('j M Y');
    }

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

    public function riskScores(): HasMany
    {
        return $this->hasMany(RiskScore::class);
    }
}
