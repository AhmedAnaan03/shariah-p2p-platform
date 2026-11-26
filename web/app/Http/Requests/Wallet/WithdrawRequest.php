<?php

namespace App\Http\Requests\Wallet;

use App\Services\WalletLedger;
use App\Support\Money;

class WithdrawRequest extends AmountRequest
{
    public const MIN_CENTS = 1_00;

    protected function limits(): array
    {
        $balance = app(WalletLedger::class)->balanceFor($this->user());

        return [
            [self::MIN_CENTS, 'The smallest withdrawal is '.Money::format(self::MIN_CENTS).'.'],
            [$balance, 'You have '.Money::format($balance).' available. Withdraw that or less.'],
        ];
    }
}
