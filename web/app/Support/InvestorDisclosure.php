<?php

namespace App\Support;

/**
 * What a borrower is told about the campaign page investors see (PB-17), on the onboarding
 * finance step and the financing request form, so nothing on that page comes as a surprise:
 * the self-declared credit history and the repayment record on the platform are shown to
 * investors, an individual borrower's name never is.
 *
 * Sent to both pages as a prop from one place, so the wording can't drift and a feature test
 * can check the notice is on each page.
 */
final class InvestorDisclosure
{
    public const TITLE = 'What investors see on your campaign page';

    /**
     * @return array{title: string, body: list<string>}
     */
    public static function forBorrower(bool $isSme): array
    {
        return [
            'title' => self::TITLE,
            'body' => [
                'When a request is approved, investors see your three credit history answers and your repayment history on Amanah (how you have repaid earlier campaigns here).',
                $isSme
                    ? 'They also see your business name, sector and years operating. They never see your own name, your income figures, your address, your ID card number or your documents.'
                    : 'You borrow as an individual, so investors never see your name: you are shown as "Individual borrower". They never see your income figures, your address, your ID card number or your documents either.',
            ],
        ];
    }
}
