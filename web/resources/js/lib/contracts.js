/**
 * The three contract types and every piece of text the request form shows about them (PB-13).
 * In one place so the wording rule can be tested: in Shariah mode the interface never says
 * "interest" or "guaranteed" (CLAUDE.md, design system "Voice and content").
 *
 * The contract cards are on screen in both modes (a borrower can switch between them), so no
 * card text uses those words. The conventional rate field is only shown while Conventional is
 * picked, which puts the page in conventional mode, so it may say "interest".
 *
 * Plain data with no React, so it is unit-tested on its own (contracts.test.js).
 */

export const CONVENTIONAL = 'conventional';
export const MURABAHAH = 'murabahah';
export const MUDARABAH = 'mudarabah';

export const CONTRACTS = [
    {
        value: CONVENTIONAL,
        label: 'Conventional',
        icon: 'bank',
        shariah: false,
        description: 'Repay the amount in instalments, plus a fixed yearly rate you offer investors.',
    },
    {
        value: MURABAHAH,
        label: 'Murabahah',
        icon: 'package',
        shariah: true,
        description: 'For buying an asset. Investors fund it at cost and sell it to you for the cost plus a fixed markup, repaid in instalments.',
    },
    {
        value: MUDARABAH,
        label: 'Mudarabah',
        icon: 'handshake',
        shariah: true,
        description: 'Investors provide the capital and share the business’s actual profit at an agreed ratio. Returns depend on that profit.',
    },
];

/** First-use explanations for the Term tooltips (README, "Voice and content"). */
export const DEFINITIONS = {
    [MURABAHAH]: 'A sale at cost plus an agreed, fixed markup.',
    [MUDARABAH]: 'Profit sharing: returns depend on the business’s actual profit.',
};

/** Shown with the contract choice. ShariahBadge pages carry it too (PB-33 extends it). */
export const SHARIAH_DISCLAIMER =
    'Shariah contracts follow the platform’s rules for Murabahah and Mudarabah. This is a rule-based check, not a formal Shariah certification.';

/**
 * The labels and hints for each contract's own terms. `{max}` and similar are filled in from
 * the server's limits by the page.
 */
export const TERM_FIELDS = {
    [CONVENTIONAL]: {
        interest_rate_percent: {
            label: 'Interest rate',
            hint: 'The yearly rate you offer investors, from 0 to {maxInterestRate}%.',
        },
    },
    [MURABAHAH]: {
        asset_description: {
            label: 'Asset being bought',
            hint: 'What the financing buys, like “Refrigerated delivery van”. Investors see this.',
        },
        markup_amount: {
            label: 'Fixed markup',
            hint: 'The amount added to the asset cost. It is agreed now and can’t change later.',
        },
        sale_price: {
            label: 'Total sale price',
            hint: 'Asset cost plus markup: what you repay over the tenure.',
        },
    },
    [MUDARABAH]: {
        investor_profit_share_percent: {
            label: 'Investors’ share of the profit',
            hint: 'From {minProfitShare}% to {maxProfitShare}%. The business keeps the rest of the profit.',
        },
        business_share: {
            label: 'Business keeps',
        },
    },
};

/** Words that must never appear on a Shariah screen. */
export const SHARIAH_FORBIDDEN_WORDS = ['interest', 'guaranteed'];

export function isShariahContract(contract) {
    return contract === MURABAHAH || contract === MUDARABAH;
}

/** The platform mode a contract puts the page in. */
export function modeForContract(contract) {
    return isShariahContract(contract) ? 'shariah' : 'conventional';
}

export function contractLabel(contract) {
    return CONTRACTS.find((c) => c.value === contract)?.label ?? contract;
}

/** "From 1% to 99%." with the server's limits put in. */
export function fillLimits(text, limits = {}) {
    return text.replace(/\{(\w+)\}/g, (match, key) => (key in limits ? String(limits[key]) : match));
}

/** Every string a Shariah screen can show from this module, for the wording test. */
export function shariahScreenText() {
    const cards = CONTRACTS.flatMap((c) => [c.label, c.description]);
    const shariahTerms = [MURABAHAH, MUDARABAH].flatMap((contract) =>
        Object.values(TERM_FIELDS[contract]).flatMap((field) => [field.label, field.hint].filter(Boolean)),
    );
    return [...cards, ...Object.values(DEFINITIONS), SHARIAH_DISCLAIMER, ...shariahTerms];
}
