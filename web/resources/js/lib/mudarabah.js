/**
 * The Mudarabah business report form's live preview (PB-29). It mirrors the server's
 * MudarabahSettlement for one period in whole cents, so the business sees what approval would
 * pay the investors before submitting. The server works it out again, under the campaign's lock,
 * when the report is approved, and that is the figure that counts.
 *
 * The classical rule: a profit first restores genuine losses the investors bore earlier; only
 * what is left is shared at the agreed ratio (the investors' share rounded down to the laari).
 * A loss moves no money: the administrator decides whether it was a genuine business loss or
 * caused by negligence or misconduct.
 *
 * Plain functions with no React, so they are unit-tested on their own (mudarabah.test.js).
 */
import { toCents } from './wallet';

/**
 * Every Mudarabah sentence written in the pages themselves (the rest comes from the server). In
 * one place so mudarabah.test.js can check that none says "interest" or "guaranteed", or reads as
 * a debt (instalment, due, owed, late, repayment).
 */
export const REPORT_TEXT = {
    finalTitle: 'This is the final period',
    final: (outstanding) => `It settles the capital: return all the capital still invested, ${outstanding}, less any genuine business loss this period.`,
    figuresTitle: 'This period’s figures',
    figuresNote: 'Enter 0 for anything that had none. Amounts in MVR.',
    previewTitle: 'What approval would do',
    previewNote: 'A preview from your figures. The administrator’s decision decides the final amounts.',
    previewEmpty: 'Enter the revenue and costs to see the result.',
    lossNote:
        'A loss shares no profit. An administrator decides whether it was a genuine business loss, borne by the investors in proportion to their capital, or caused by negligence or misconduct, which the business is responsible for.',
    walletShort: (balance) => `Your wallet holds ${balance}. Top up before the report is approved, or approval can’t pay the investors.`,
    documentTitle: 'Supporting document',
    documentNote: 'Optional: accounts or a sales summary for the period. Stored privately and encrypted; only you and administrators can open it.',
    submit: 'Submit report for review',
    cardTitle: 'Business reports',
    noReportsTitle: 'No reports yet',
    noReports: 'The first report can be submitted on the first planned date.',
    settledTitle: 'Capital settled',
    settled: 'The investors’ capital is settled: returned to them, or reduced by a genuine business loss.',
    accountTitle: 'Your capital account',
    noApprovedTitle: 'No approved reports yet',
    noApproved: 'The business reports each period on its planned date. Your part appears here once a report is approved.',
    waiting: 'Waiting for an administrator',
};

/**
 * @param {{ revenue: unknown, costs: unknown, capitalReturned: unknown, unrestored: unknown, investorShareBp: number }} input
 * @returns {{ ready: boolean, profit: number, isLoss: boolean, restored: number, investorsProfit: number, businessProfit: number, capital: number, payment: number }}
 *          amounts in cents
 */
export function reportPreview({ revenue, costs, capitalReturned, unrestored, investorShareBp }) {
    const revenueCents = toCents(revenue);
    const costsCents = toCents(costs);
    const capital = Math.max(0, toCents(capitalReturned) ?? 0);
    const ready = revenueCents !== null && costsCents !== null;
    const profit = ready ? revenueCents - costsCents : 0;

    let restored = 0;
    let investorsProfit = 0;
    let businessProfit = 0;
    if (profit > 0) {
        restored = Math.min(profit, Math.max(0, toCents(unrestored) ?? 0));
        const left = profit - restored;
        investorsProfit = Math.floor((left * investorShareBp) / 10000);
        businessProfit = left - investorsProfit;
    }

    return { ready, profit, isLoss: profit < 0, restored, investorsProfit, businessProfit, capital, payment: capital + restored + investorsProfit };
}

/** "Profit", "Loss" or "No profit or loss", for the live result. */
export function resultLabel(profitCents) {
    if (profitCents > 0) return 'Profit';
    if (profitCents < 0) return 'Loss';
    return 'No profit or loss';
}
