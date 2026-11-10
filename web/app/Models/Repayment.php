<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['amount', 'paid_at'])]
class Repayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
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
}
