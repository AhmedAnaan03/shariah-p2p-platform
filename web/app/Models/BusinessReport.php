<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Mudarabah business's report for one planned capital return period (PB-29): revenue, costs,
 * the profit or loss (revenue − costs, CHECK), the capital it returns, and an optional
 * supporting document. An administrator approves it or rejects it with a reason; for a loss the
 * administrator also decides whether it was a genuine business loss (borne by the investors in
 * proportion to their capital) or caused by negligence or misconduct (the business is liable and
 * the capital stays to be restored).
 *
 * The outcome columns are filled on approval by BusinessReportService from MudarabahSettlement,
 * and the database checks they account for the whole profit or loss. Nothing here is a fixed or
 * expected figure: only what the business reported and what was done with it.
 */
#[Fillable(['revenue', 'costs', 'profit', 'capital_returned', 'submitted_at'])]
class BusinessReport extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** A genuine business loss: the investors bear it in proportion to their capital. */
    public const CAUSE_BUSINESS = 'business';

    /** Caused by negligence or misconduct: the business is liable for it. */
    public const CAUSE_NEGLIGENCE = 'negligence';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_SUBMITTED,
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'costs' => 'decimal:2',
            'profit' => 'decimal:2',
            'capital_returned' => 'decimal:2',
            'loss_restored' => 'decimal:2',
            'investors_profit' => 'decimal:2',
            'business_profit' => 'decimal:2',
            'loss_borne' => 'decimal:2',
            'business_liable_loss' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isLoss(): bool
    {
        return str_starts_with((string) $this->profit, '-');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function repaymentSchedule(): BelongsTo
    {
        return $this->belongsTo(RepaymentSchedule::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KycDocument::class, 'kyc_document_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(InvestorPayout::class);
    }
}
