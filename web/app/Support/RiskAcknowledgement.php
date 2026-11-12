<?php

namespace App\Support;

/**
 * The investor suitability acknowledgement (PB-10): what an investor must read and accept
 * before they can pledge.
 *
 * Kept in one place so the page shows exactly this text and the audit log records exactly
 * which version was accepted. Change VERSION whenever the wording changes.
 */
final class RiskAcknowledgement
{
    public const VERSION = '2026-11-v1';

    public const TITLE = 'Before you invest: understand the risks';

    /**
     * Plain language, one idea per point, the most important first.
     *
     * @var list<array{title: string, text: string}>
     */
    public const POINTS = [
        [
            'title' => 'Your money is at risk.',
            'text' => 'If a business or person you fund cannot repay, you could lose some or all of the money you put in.',
        ],
        [
            'title' => 'Returns are not guaranteed.',
            'text' => 'Any return shown is an expectation, not a promise. Under a Mudarabah contract your return depends on the profit the business actually makes, and a loss reduces what you get back.',
        ],
        [
            'title' => 'Your money is tied up.',
            'text' => 'Once a campaign is funded you cannot take your pledge back. You are repaid in instalments over the full term of the financing.',
        ],
        [
            'title' => 'There is no deposit protection.',
            'text' => 'Money you invest here is not a bank deposit and is not covered by any government or deposit guarantee scheme.',
        ],
        [
            'title' => 'Spread your risk.',
            'text' => 'Putting small amounts into several campaigns is safer than putting everything into one.',
        ],
        [
            'title' => 'Only invest money you can afford to lose.',
            'text' => 'Do not invest money you need for living costs, debts or emergencies.',
        ],
    ];

    public const CONFIRMATION = 'I have read and understood these risks, and I accept that I could lose some or all of the money I invest.';

    /**
     * For the React page.
     *
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'title' => self::TITLE,
            'points' => self::POINTS,
            'confirmation' => self::CONFIRMATION,
        ];
    }
}
