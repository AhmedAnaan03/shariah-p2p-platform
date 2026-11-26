<?php

namespace Tests\Concerns;

/**
 * The wording rules the Shariah tests check (CLAUDE.md, design system "Voice and content").
 */
final class ShariahWording
{
    /** Never in Shariah mode. */
    public const FORBIDDEN = '/\b(interest|guaranteed)\b/i';

    /**
     * Never about a Mudarabah's schedule (PB-34 review): it is planned capital returns, not a
     * debt, so nothing calls a row an instalment, due, owed, late or a repayment.
     */
    public const DEBT = '/\b(instalments?|due|owed?|owes|owing|debts?|late|overdue|repay\w*)\b/i';
}
