/**
 * Wallet and pledge wording and arithmetic for the pages (PB-18, PB-19). Amounts come from the
 * server as exact "12500.00" strings; these work in whole cents so nothing is lost to rounding.
 * Plain functions with no React, so they are unit-tested on their own (wallet.test.js).
 */
import { formatMoney, toNumber } from './format';

/** Shown on the wallet card and every money confirmation (README, "Voice and content"). */
export const SIMULATED = 'Simulated wallet, no real money.';

/** "12500.50" or 12500.5 → 1250050; anything unusable → null. */
export function toCents(amount) {
    const number = toNumber(amount);
    return number === null ? null : Math.round(number * 100);
}

/**
 * Why a typed pledge amount can't be sent yet, or null when it can: the same rules the server
 * applies (and re-checks under the campaign's lock).
 */
export function pledgeProblem(amount, { balance, remaining, minimum }) {
    const cents = toCents(amount);
    if (cents === null || cents <= 0) {
        return 'Enter an amount to pledge.';
    }
    if (cents < toCents(minimum)) {
        return `The smallest pledge is ${formatMoney(minimum)}.`;
    }
    if (cents > toCents(remaining)) {
        return `Only ${formatMoney(remaining)} is left to raise. Pledge that or less.`;
    }
    if (cents > toCents(balance)) {
        return `Your wallet has ${formatMoney(balance)}. Top up first, or pledge that or less.`;
    }
    return null;
}

/** "Pledge MVR 5,000" for the button, with decimals only when there are some. */
export function pledgeLabel(amount) {
    const cents = toCents(amount);
    if (cents === null || cents <= 0) {
        return 'Pledge';
    }
    return `Pledge ${formatMoney(cents / 100, { decimals: cents % 100 === 0 ? 0 : 2 })}`;
}

/** What a pledge does, said before it happens. */
export function pledgeConsequence(deadlineLabel) {
    return `The money moves from your wallet into the campaign's escrow. If the campaign doesn't reach its target by the end of ${deadlineLabel}, it comes back to your wallet.`;
}
