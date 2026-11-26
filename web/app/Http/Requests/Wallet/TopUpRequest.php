<?php

namespace App\Http\Requests\Wallet;

use App\Services\WalletLedger;
use App\Support\Money;

class TopUpRequest extends AmountRequest
{
    public const MIN_CENTS = 10_00;

    protected function limits(): array
    {
        return [
            [self::MIN_CENTS, 'The smallest top-up is '.Money::format(self::MIN_CENTS).'.'],
            [WalletLedger::MAX_TOP_UP_CENTS, 'A top-up can be at most '.Money::format(WalletLedger::MAX_TOP_UP_CENTS).'.'],
        ];
    }
}
