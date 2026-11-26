<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ledger entry. Only WalletLedger (PB-18) creates these: always two per transfer (a debit
 * and a credit of the same amount sharing transfer_ref), inside a DB transaction. Never updated.
 */
#[Fillable([
    'wallet_type', 'transfer_ref', 'type', 'direction', 'amount', 'balance_after',
    'pledge_id', 'repayment_id', 'business_report_id', 'description',
])]
class WalletTransaction extends Model
{
    // Append-only table: it has created_at but no updated_at column.
    public const UPDATED_AT = null;

    public const TOP_UP = 'top_up';

    public const WITHDRAWAL = 'withdrawal';

    public const PLEDGE = 'pledge';

    public const PLEDGE_REFUND = 'pledge_refund';

    /** A funded campaign's escrow paid out to the borrower's wallet, in one transfer (PB-34). */
    public const DISBURSEMENT = 'disbursement';

    /** A borrower pays one instalment into the campaign's escrow (PB-35). */
    public const REPAYMENT = 'repayment';

    /** The escrow pays one investor their share of a repayment (PB-36). */
    public const RETURN_PAYOUT = 'return_payout';

    /**
     * A Mudarabah business pays an approved report's money into the escrow: returned capital,
     * restored losses and the investors' profit share (PB-29). Never called a repayment.
     */
    public const MUDARABAH_PAYMENT = 'mudarabah_payment';

    /** The escrow pays one investor their part of an approved Mudarabah report (PB-29). */
    public const MUDARABAH_PAYOUT = 'mudarabah_payout';

    public const CREDIT = 'credit';

    public const DEBIT = 'debit';

    /** How each type reads in a wallet's history. */
    public const LABELS = [
        self::TOP_UP => 'Top-up',
        self::WITHDRAWAL => 'Withdrawal',
        self::PLEDGE => 'Pledge',
        self::PLEDGE_REFUND => 'Pledge refund',
        self::DISBURSEMENT => 'Disbursement',
        self::REPAYMENT => 'Repayment',
        self::RETURN_PAYOUT => 'Return payout',
        self::MUDARABAH_PAYMENT => 'Mudarabah: capital and profit to investors',
        self::MUDARABAH_PAYOUT => 'Mudarabah: capital and profit received',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function walletAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class);
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
