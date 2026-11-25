/**
 * Campaign card and campaign page helpers (PB-16, PB-17): the label above a campaign's return
 * figure, how funded it is, how many days are left, and the fixed text the investor's campaign
 * page shows. Plain functions and data with no React, so they are unit-tested on their own
 * (campaigns.test.js), including the Shariah wording rule.
 */
import { CONVENTIONAL, MUDARABAH, MURABAHAH } from './contracts';
import { daysLeftText, toNumber } from './format';

/**
 * The label above the card's return figure follows the contract (components/CampaignCard.md).
 * The figure itself comes from the server with its unit: "8.5% a year", "8.0% in total"
 * (a Murabahah markup is for the whole tenure, never a yearly rate), "60 : 40".
 */
export const RATE_LABELS = {
    [CONVENTIONAL]: 'Interest',
    [MURABAHAH]: 'Markup',
    [MUDARABAH]: 'Profit share',
};

export function rateLabel(contract) {
    return RATE_LABELS[contract] ?? 'Return';
}

/**
 * How much of the target has been pledged, as a whole percentage from 0 to 100, for progress
 * bars and the funding ring. An unusable or zero target gives 0.
 */
export function fundingPercent(raised, target) {
    const got = toNumber(raised);
    const goal = toNumber(target);
    if (got === null || goal === null || goal <= 0 || got <= 0) {
        return 0;
    }
    return Math.min(100, Math.floor((got / goal) * 100));
}

/** "0 investors", "1 investor", "12 investors". */
export function investorsText(count) {
    const n = toNumber(count) ?? 0;
    return n === 1 ? '1 investor' : `${n} investors`;
}

/**
 * The investor's campaign page text that doesn't come from the server. On a Shariah campaign it
 * never says "interest" or "guaranteed" (README, "Voice and content"), so the same words are
 * used for every contract.
 */
export const CAMPAIGN_PAGE_TEXT = {
    riskWarning: 'You could lose some or all of your money. Your returns could be lower than expected, or you could receive nothing. Only pledge what you can afford to lose.',
    riskReservedTitle: 'Not yet scored',
    riskReserved: 'This campaign hasn’t been given an AI-assisted risk grade yet. The grade, and the factors behind it, will appear here once it is scored.',
    riskExplanationTitle: 'Why this grade',
    riskExplanationReserved: 'The factors that raise or lower this campaign’s risk will be listed here.',
    fundingNote: 'Pledging opens in a later release. Until then every campaign shows 0% funded.',
    repaymentTitle: 'Repayment history on Amanah',
    creditTitle: 'Credit history, as declared by the borrower',
    creditNote: 'The borrower’s own answers. An administrator checked them against the borrower’s MMA credit report, where one was attached, before approving the campaign.',
};

/** Every fixed string the card and campaign page show on a Shariah campaign, for the wording test. */
export function shariahCampaignText() {
    return [RATE_LABELS[MURABAHAH], RATE_LABELS[MUDARABAH], ...Object.values(CAMPAIGN_PAGE_TEXT), daysLeftText(3), investorsText(2)];
}
