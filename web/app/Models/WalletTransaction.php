<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ledger entry. Only the wallet ledger code (PB-18) creates these, inside a DB transaction.
 */
#[Fillable([
    'transfer_ref', 'type', 'direction', 'amount', 'balance_after',
    'pledge_id', 'repayment_id', 'description',
])]
class WalletTransaction extends Model
{
    // Append-only table: it has created_at but no updated_at column.
    public const UPDATED_AT = null;

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
