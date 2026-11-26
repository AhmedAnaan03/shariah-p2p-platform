import { describe, expect, it } from 'vitest';
import { REPORT_TEXT, reportPreview, resultLabel } from './mudarabah';

// The same rules as tests/Concerns/ShariahWording.php.
const FORBIDDEN = /\b(interest|guaranteed)\b/i;
const DEBT = /\b(instalments?|due|owed?|owes|owing|debts?|late|overdue|repay\w*)\b/i;

// PB-29: 60 : 40 ratio (6000 basis points) unless a case says otherwise.
const base = { capitalReturned: '0', unrestored: '0', investorShareBp: 6000 };

describe('reportPreview', () => {
    it('TC1 blank figures are not ready and preview nothing', () => {
        expect(reportPreview({ ...base, revenue: null, costs: '' })).toMatchObject({ ready: false, profit: 0, payment: 0 });
    });

    it('TC2 a profit with no earlier loss is shared at the ratio', () => {
        expect(reportPreview({ ...base, revenue: '5000', costs: '3000', capitalReturned: '3333.33' })).toEqual({
            ready: true,
            profit: 200000,
            isLoss: false,
            restored: 0,
            investorsProfit: 120000,
            businessProfit: 80000,
            capital: 333333,
            payment: 453333,
        });
    });

    it('TC3 a profit smaller than the earlier loss only restores capital', () => {
        expect(reportPreview({ ...base, revenue: '3500', costs: '3000', unrestored: '1000' })).toMatchObject({ restored: 50000, investorsProfit: 0, businessProfit: 0, payment: 50000 });
    });

    it('TC4 a profit larger than the earlier loss restores it first and shares the rest', () => {
        expect(reportPreview({ ...base, revenue: '4500', costs: '3000', unrestored: '1000' })).toMatchObject({ restored: 100000, investorsProfit: 30000, businessProfit: 20000 });
    });

    it('TC5 a loss moves no profit, only the capital returned', () => {
        expect(reportPreview({ ...base, revenue: '2000', costs: '3000', capitalReturned: '500' })).toMatchObject({ profit: -100000, isLoss: true, investorsProfit: 0, payment: 50000 });
    });

    it('TC6 the investors share is rounded down to the laari', () => {
        expect(reportPreview({ ...base, revenue: '100.01', costs: '0' })).toMatchObject({ investorsProfit: 6000, businessProfit: 4001 });
    });
});

describe('resultLabel', () => {
    it('names the result', () => {
        expect([resultLabel(1), resultLabel(-1), resultLabel(0)]).toEqual(['Profit', 'Loss', 'No profit or loss']);
    });
});

describe('REPORT_TEXT', () => {
    it('never says interest or guaranteed, and never reads as a debt', () => {
        const texts = Object.values(REPORT_TEXT).map((text) => (typeof text === 'function' ? text('MVR 1,000.00') : text));
        for (const text of texts) {
            expect(text).not.toMatch(FORBIDDEN);
            expect(text).not.toMatch(DEBT);
        }
        expect(texts).toHaveLength(Object.keys(REPORT_TEXT).length);
    });
});
