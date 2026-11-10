<?php

namespace App\Models;

use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    public function isShariah(): bool
    {
        return in_array($this->contract_type, [self::CONTRACT_MURABAHAH, self::CONTRACT_MUDARABAH], true);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
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
