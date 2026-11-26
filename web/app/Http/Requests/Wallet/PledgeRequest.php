<?php

namespace App\Http\Requests\Wallet;

use App\Models\Campaign;
use App\Services\PledgeService;
use App\Services\WalletLedger;
use App\Support\Money;

/**
 * A pledge's amount (PB-19): at least MVR 100 (or what is left, if less), no more than the
 * remaining amount and no more than the wallet's balance. Who may pledge is the route's
 * ->can('pledge', 'campaign'); this adds the verified-account check of every money movement.
 */
class PledgeRequest extends AmountRequest
{
    protected function limits(): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->route('campaign');
        $remaining = PledgeService::remainingCents($campaign);
        $balance = app(WalletLedger::class)->balanceFor($this->user());
        $minimum = min(PledgeService::MIN_CENTS, $remaining);

        // The lower of the two caps decides, with the message for that cap.
        $max = $balance < $remaining
            ? [$balance, 'Your wallet has '.Money::format($balance).'. Top up first, or pledge that or less.']
            : [$remaining, 'Only '.Money::format($remaining).' is left to raise. Pledge that or less.'];

        return [[$minimum, 'The smallest pledge is '.Money::format($minimum).'.'], $max];
    }
}
