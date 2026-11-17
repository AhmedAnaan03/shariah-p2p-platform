<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\CampaignFinancials;

/**
 * Turns a campaign into the props its pages need (borrower, admin and marketplace), so every
 * page labels the same field the same way. Only the fields a page shows are sent, never the
 * whole model.
 */
final class CampaignPresenter
{
    public const CONTRACT_LABELS = [
        Campaign::CONTRACT_CONVENTIONAL => 'Conventional',
        Campaign::CONTRACT_MURABAHAH => 'Murabahah',
        Campaign::CONTRACT_MUDARABAH => 'Mudarabah',
    ];

    /**
     * What a list row needs.
     *
     * @return array<string, mixed>
     */
    public static function summary(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'purpose' => $campaign->purpose,
            'purpose_label' => Campaign::PURPOSES[$campaign->purpose],
            'amount_requested' => (float) $campaign->amount_requested,
            'tenure_months' => $campaign->tenure_months,
            'contract_type' => $campaign->contract_type,
            'contract_label' => self::CONTRACT_LABELS[$campaign->contract_type],
            'is_shariah' => $campaign->isShariah(),
            'status' => $campaign->status,
            'status_reason' => $campaign->status_reason,
            'submitted_at' => $campaign->submitted_at?->toIso8601String(),
            'approved_at' => $campaign->approved_at?->toIso8601String(),
            'funding_deadline' => $campaign->funding_deadline?->toIso8601String(),
            // The deadline's date in the Maldives ("18 Dec 2026"), so every viewer sees the same
            // date whatever their browser's time zone. The ISO time above is for counting days.
            'funding_deadline_label' => $campaign->fundingDeadlineLabel(),
        ];
    }

    /**
     * Everything about the request itself, for a detail page.
     *
     * @return array<string, mixed>
     */
    public static function details(Campaign $campaign): array
    {
        return [
            ...self::summary($campaign),
            'description' => $campaign->description,
            'business_background' => $campaign->business_background,
            'terms' => $campaign->termsForDisplay(),
        ];
    }

    /**
     * The borrower's finances as label / value / mono rows (PB-14).
     *
     * @return list<array{label: string, value: string, mono: bool}>
     */
    public static function finances(CampaignFinancials $financials): array
    {
        return [
            ['label' => 'Annual income', 'value' => self::money($financials->annual_income), 'mono' => true],
            ['label' => 'Monthly repayments on existing loans and cards', 'value' => self::money($financials->monthly_debt_repayments), 'mono' => true],
            ['label' => 'Time in current job or business', 'value' => CampaignFinancials::employmentLabel($financials->employment_years), 'mono' => false],
            ['label' => 'Housing', 'value' => CampaignFinancials::HOUSING[$financials->housing], 'mono' => false],
        ];
    }

    /**
     * The three self-declared credit history answers (PB-14), worded as the questions the
     * borrower answered, so an administrator can compare them with the MMA credit report.
     *
     * @return list<array{label: string, value: string, mono: bool}>
     */
    public static function creditHistory(CampaignFinancials $financials): array
    {
        return [
            ['label' => 'Open loans, financing facilities and credit cards', 'value' => (string) $financials->open_credit_lines, 'mono' => true],
            ['label' => 'Repayments 30 or more days late in the past 2 years', 'value' => (string) $financials->late_payments_2y, 'mono' => true],
            ['label' => 'Court judgments, bankruptcies or debts written off', 'value' => (string) $financials->public_records, 'mono' => true],
        ];
    }

    private static function money(mixed $value): string
    {
        return 'MVR '.number_format((float) $value, 2);
    }
}
