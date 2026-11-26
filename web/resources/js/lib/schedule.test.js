import { describe, expect, it } from 'vitest';
import { chartData, chartLegend, chartTable, isCapitalReturn, nextRow, scheduleTakeaway, SCHEDULE_STATES, timelineItems } from './schedule';

/** PB-34, PB-28: reshaping the server's schedule for the timeline, chart and chart table. */
const DEBT_LABELS = { kind: 'instalments', title: 'Repayment schedule', row: 'Instalment', rows: 'Instalments', date: 'Due date', capital: 'Capital', return: 'Markup', amount: 'Instalment' };
const CAPITAL_LABELS = { kind: 'capital_returns', title: 'Planned capital return schedule', row: 'Planned capital return', rows: 'Planned capital returns', date: 'Planned date', capital: 'Planned capital return', return: null, amount: 'Planned capital return' };

const row = (overrides) => ({ id: 1, label: 'Instalment 1', dateLabel: '26 Dec 2026', monthLabel: 'Dec 2026', capital: '3333.33', return: '333.33', amount: '3666.66', state: 'upcoming', stateLabel: 'Due 26 Dec 2026', ...overrides });

const murabahah = {
    labels: DEBT_LABELS,
    count: 3,
    capitalTotal: '10000.00',
    returnTotal: '1000.00',
    total: '11000.00',
    firstDateLabel: '26 Dec 2026',
    rows: [row({ state: 'paid' }), row({ id: 2, label: 'Instalment 2' }), row({ id: 3, label: 'Instalment 3', capital: '3333.34', return: '333.34', amount: '3666.68' })],
};
const mudarabah = {
    labels: CAPITAL_LABELS,
    count: 1,
    capitalTotal: '20000.00',
    returnTotal: null,
    total: '20000.00',
    firstDateLabel: '26 Dec 2026',
    rows: [row({ label: 'Planned capital return 1', capital: '20000.00', return: null, amount: '20000.00', state: 'awaiting', stateLabel: 'Planned date passed, awaiting business report' })],
};

describe('schedule', () => {
    it('tells a Mudarabah capital return schedule from a debt', () => {
        expect(isCapitalReturn(mudarabah)).toBe(true);
        expect(isCapitalReturn(murabahah)).toBe(false);
        expect(isCapitalReturn(null)).toBe(false);
    });

    it('turns rows into chart numbers, with no return for a Mudarabah', () => {
        expect(chartData(murabahah.rows)[2]).toEqual({ label: 'Dec 2026', capital: 3333.34, ret: 333.34 });
        expect(chartData(mudarabah.rows)).toEqual([{ label: 'Dec 2026', capital: 20000, ret: 0 }]);
        expect(chartData(undefined)).toEqual([]);
    });

    it('has a return series in the legend only when the contract has one', () => {
        expect(chartLegend(DEBT_LABELS).map((item) => item.label)).toEqual(['Capital', 'Markup']);
        expect(chartLegend(CAPITAL_LABELS).map((item) => item.label)).toEqual(['Planned capital return']);
    });

    it('builds the chart table with the same figures', () => {
        expect(chartTable(murabahah).columns).toEqual(['Instalment', 'Due date', 'Capital', 'Markup', 'Total']);
        expect(chartTable(murabahah).rows[2]).toEqual(['Instalment 3', '26 Dec 2026', 'MVR 3,333.34', 'MVR 333.34', 'MVR 3,666.68']);
        expect(chartTable(mudarabah).columns).toEqual(['Planned capital return', 'Planned date', 'Planned capital return']);
        expect(chartTable(mudarabah).rows[0]).toEqual(['Planned capital return 1', '26 Dec 2026', 'MVR 20,000.00']);
    });

    it('sums up the schedule in one line, never as a debt for a Mudarabah', () => {
        expect(scheduleTakeaway(murabahah)).toBe('MVR 11,000.00 over 3 months: MVR 10,000.00 capital and MVR 1,000.00 markup.');
        expect(scheduleTakeaway(mudarabah)).toBe('MVR 20,000.00 of capital planned to be returned over 1 month, from 26 Dec 2026.');
        expect(scheduleTakeaway(mudarabah)).not.toMatch(/\b(instalments?|due|owed?|late|repay\w*|interest|guaranteed)\b/i);
    });

    it('gives the timeline the server’s words for each row', () => {
        expect(timelineItems(mudarabah.rows)).toEqual([
            { id: 1, label: 'Planned capital return 1', state: 'awaiting', stateLabel: 'Planned date passed, awaiting business report', amount: '20000.00' },
        ]);
    });

    it('finds the next unpaid row', () => {
        expect(nextRow(murabahah.rows).label).toBe('Instalment 2');
        expect(nextRow([row({ state: 'paid' })])).toBeNull();
        expect(nextRow([])).toBeNull();
    });

    it('has a look for every state, and awaiting is not shown as late', () => {
        expect(Object.keys(SCHEDULE_STATES)).toEqual(['paid', 'due', 'upcoming', 'late', 'awaiting']);
        expect(SCHEDULE_STATES.awaiting.tone).not.toBe(SCHEDULE_STATES.late.tone);
    });
});
