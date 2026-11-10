<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's wallet (type "user") or a campaign's escrow (type "escrow"). Exactly one owner,
 * enforced by the wallet_accounts_owner_check constraint.
 */
#[Fillable(['type'])]
class WalletAccount extends Model
{
    public const TYPE_USER = 'user';

    public const TYPE_ESCROW = 'escrow';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
