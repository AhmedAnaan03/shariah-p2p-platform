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
    'pledge_id', 'repayment_id', 'description',
])]
class WalletTransaction extends Model
{
    // Append-only table: it has created_at but no updated_at column.
    public const UPDATED_AT = null;

    public const TOP_UP = 'top_up';

    public const WITHDRAWAL = 'withdrawal';

    public const PLEDGE = 'pledge';

    public const PLEDGE_REFUND = 'pledge_refund';

    public const CREDIT = 'credit';

    public const DEBIT = 'debit';

    /** How each type reads in a wallet's history. */
    public const LABELS = [
        self::TOP_UP => 'Top-up',
        self::WITHDRAWAL => 'Withdrawal',
        self::PLEDGE => 'Pledge',
        self::PLEDGE_REFUND => 'Pledge refund',
        'disbursement' => 'Disbursement',
        'repayment' => 'Repayment',
        'return_payout' => 'Return payout',
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
}
