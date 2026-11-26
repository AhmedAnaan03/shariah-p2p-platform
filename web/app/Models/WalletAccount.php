<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's wallet (type "user"), a campaign's escrow (type "escrow"), or the one "external"
 * account that stands for the outside world (a simulated bank, PB-18). Exactly one owner,
 * enforced by the wallet_accounts_owner_check constraint.
 *
 * There is no balance column: a balance is derived from the ledger (WalletLedger::balance). Only
 * WalletLedger writes ledger entries, always in pairs and inside a transaction.
 */
#[Fillable(['type', 'user_id', 'campaign_id', 'singleton'])]
class WalletAccount extends Model
{
    public const TYPE_USER = 'user';

    public const TYPE_ESCROW = 'escrow';

    public const TYPE_EXTERNAL = 'external';

    /**
     * The user's wallet, created on first use. Looked up first (a plain read never waits on a
     * lock); only a missing wallet is created, and createOrFirst() relies on the unique user_id, so
     * two requests creating it at the same moment still end up with one wallet.
     */
    public static function forUser(User $user): self
    {
        return self::where('user_id', $user->id)->first()
            ?? self::createOrFirst(['user_id' => $user->id], ['type' => self::TYPE_USER]);
    }

    /**
     * The campaign's escrow wallet, created on first use (unique campaign_id).
     */
    public static function escrowFor(Campaign $campaign): self
    {
        return self::where('campaign_id', $campaign->id)->first()
            ?? self::createOrFirst(['campaign_id' => $campaign->id], ['type' => self::TYPE_ESCROW]);
    }

    /**
     * The outside world: top-ups come from it and withdrawals go to it. Its balance is minus the
     * money users have brought in, so it is the one account allowed below zero.
     */
    public static function external(): self
    {
        return self::where('singleton', 1)->first()
            ?? self::createOrFirst(['singleton' => 1], ['type' => self::TYPE_EXTERNAL]);
    }

    public function isExternal(): bool
    {
        return $this->type === self::TYPE_EXTERNAL;
    }

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
