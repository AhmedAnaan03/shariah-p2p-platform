<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One instalment paid from the borrower's wallet (PB-35): conventional or Murabahah only. A
 * Mudarabah is never repaid; its money comes from approved business reports (BusinessReport).
 *
 * Instalments are paid whole: one repayment per schedule row (UNIQUE). amount = capital_amount +
 * return_amount (CHECK). A conventional early payoff charges no interest for periods not reached
 * yet, so those rows have return_amount 0 and kind early_payoff.
 */
#[Fillable(['amount', 'capital_amount', 'return_amount', 'kind', 'paid_at'])]
class Repayment extends Model
{
    public const KIND_INSTALMENT = 'instalment';

    public const KIND_EARLY_PAYOFF = 'early_payoff';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'capital_amount' => 'decimal:2',
            'return_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function repaymentSchedule(): BelongsTo
    {
        return $this->belongsTo(RepaymentSchedule::class);
    }

    /**
     * The borrower's debit and the investors' return payouts for this repayment.
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Each investor's share, capital and return separately (PB-36).
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(InvestorPayout::class);
    }
}
