import { describe, expect, it } from 'vitest';
import { cleanNumberInput, daysLeftLabel, formatAmount, formatBytes, formatCompactMoney, formatDate, formatMoney, formatPercent, formatTime, initials } from './format';

// Each case: [input, expected]. Grouped as blank, invalid and valid input (report table).
const cases = (fn, table) => it.each(table)('%j → %s', (input, expected) => expect(fn(...[].concat(input))).toBe(expected));

describe('formatMoney', () => {
    cases(formatMoney, [
        // blank
        [null, '—'],
        ['', '—'],
        // invalid
        ['twelve', '—'],
        [Number.NaN, '—'],
        [Number.POSITIVE_INFINITY, '—'],
        // valid
        [12500, 'MVR 12,500.00'],
        ['12500.5', 'MVR 12,500.50'],
        ['1,250', 'MVR 1,250.00'],
        [0, 'MVR 0.00'],
        [-300, 'MVR −300.00'],
        [0.005, 'MVR 0.01'],
        [[5000, { decimals: 0 }], 'MVR 5,000'],
        [[1200, { sign: true }], 'MVR +1,200.00'],
        [[1200, { code: 'USD' }], 'USD 1,200.00'],
    ]);
});

describe('formatAmount', () => {
    cases(formatAmount, [
        [undefined, '—'],
        [[-0.001, { sign: true }], '0.00'],
        [[-1200, { sign: true }], '−1,200.00'],
        [1234567.891, '1,234,567.89'],
    ]);
});

describe('formatCompactMoney', () => {
    cases(formatCompactMoney, [
        [null, '—'],
        ['abc', '—'],
        [950, 'MVR 950'],
        [12500, 'MVR 12.5K'],
        [12000, 'MVR 12K'],
        [1250000, 'MVR 1.3M'],
        [-4200, 'MVR −4.2K'],
    ]);
});

describe('formatPercent', () => {
    cases(formatPercent, [
        ['', '—'],
        ['n/a', '—'],
        [12.43, '12.4%'],
        [8, '8.0%'],
        [[60, { decimals: 0 }], '60%'],
    ]);
});

describe('formatDate', () => {
    cases(formatDate, [
        [null, '—'],
        ['', '—'],
        ['not a date', '—'],
        ['2026-13-45', '—'],
        ['2026-11-12', '12 Nov 2026'],
        ['2026-09-01', '1 Sep 2026'],
        [new Date(2026, 0, 31), '31 Jan 2026'],
    ]);

    it('reads a full ISO date-time in local time', () => {
        const local = new Date(2026, 10, 12, 9, 30);
        expect(formatDate(local.toISOString())).toBe('12 Nov 2026');
    });
});

describe('formatTime', () => {
    cases(formatTime, [
        [null, '—'],
        ['soon', '—'],
        [new Date(2026, 10, 12, 14, 30), '14:30'],
        [new Date(2026, 10, 12, 9, 5), '09:05'],
    ]);
});

describe('formatBytes', () => {
    cases(formatBytes, [
        [null, '—'],
        [-1, '—'],
        [10, '1 KB'],
        [839680, '820 KB'],
        [7549747, '7.2 MB'],
    ]);
});

describe('initials', () => {
    cases(initials, [
        [null, '?'],
        ['   ', '?'],
        ['aishath', 'A'],
        ['Aishath Rasheed', 'AR'],
        ['Mohamed Ali Didi', 'MD'],
    ]);
});

describe('cleanNumberInput', () => {
    cases(cleanNumberInput, [
        // blank
        [null, ''],
        ['', ''],
        // invalid characters are dropped
        ['abc', ''],
        ['MVR 12,500', '12500'],
        ['-500', '500'],
        ['1.2.3', '1.23'],
        // valid
        ['120000', '120000'],
        ['120,000.50', '120000.50'],
        ['120000.505', '120000.50'],
        ['120.', '120.'],
        [['8.567', { decimals: 1 }], '8.5'],
        [['12.5', { decimals: 0 }], '12'],
    ]);
});

describe('daysLeftLabel', () => {
    // Approved on 18 Nov at 10:00 with a deadline at the end of 18 Dec (30 days).
    const deadline = '2026-12-18T23:59:59';
    const at = (when) => new Date(when);

    it.each([
        // blank and invalid
        [null, at('2026-11-18T10:00:00'), '—'],
        ['not a date', at('2026-11-18T10:00:00'), '—'],
        // valid
        [deadline, at('2026-11-18T10:00:00'), '30 days left'],
        [deadline, at('2026-12-17T10:00:00'), '1 day left'],
        [deadline, at('2026-12-18T10:00:00'), 'Closes today'],
        [deadline, at('2026-12-19T00:00:01'), 'Closed'],
    ])('%s at %s → %s', (value, now, expected) => expect(daysLeftLabel(value, now)).toBe(expected));
});
