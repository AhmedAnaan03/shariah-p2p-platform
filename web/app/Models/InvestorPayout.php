<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One investor's share of one repayment (PB-36) or one approved Mudarabah report (PB-29), every
 * part recorded separately: capital, restored loss, return (interest, markup or profit share) and,
 * for a Mudarabah, a genuine loss allocated to them. The money parts were paid as one ledger
 * transfer (transfer_ref); a pure loss allocation moves no money. Append-only.
 */
#[Fillable([
    'campaign_id', 'investor_id', 'pledge_id', 'repayment_id', 'business_report_id',
    'capital_amount', 'loss_restored_amount', 'return_amount', 'loss_amount', 'transfer_ref',
])]
class InvestorPayout extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'capital_amount' => 'decimal:2',
            'loss_restored_amount' => 'decimal:2',
            'return_amount' => 'decimal:2',
            'loss_amount' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
    }

    public function pledge(): BelongsTo
    {
        return $this->belongsTo(Pledge::class);
    }

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(Repayment::class);
    }

    public function businessReport(): BelongsTo
    {
        return $this->belongsTo(BusinessReport::class);
    }
}
