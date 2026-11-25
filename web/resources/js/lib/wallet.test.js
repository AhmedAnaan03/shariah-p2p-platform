import { describe, expect, it } from 'vitest';
import { SIMULATED, pledgeConsequence, pledgeLabel, pledgeProblem, toCents } from './wallet';

const LIMITS = { balance: '3000.00', remaining: '2000.00', minimum: '100.00' };

describe('toCents', () => {
    it('turns amounts into whole cents, or null', () => {
        expect(toCents('12500.50')).toBe(1250050);
        expect(toCents(0.1 + 0.2)).toBe(30);
        expect(toCents('')).toBeNull();
        expect(toCents('abc')).toBeNull();
    });
});

describe('pledgeProblem (the same rules as the server)', () => {
    it.each([
        [null, 'Enter an amount to pledge.'],
        ['', 'Enter an amount to pledge.'],
        [0, 'Enter an amount to pledge.'],
        [-5, 'Enter an amount to pledge.'],
        [99.99, 'The smallest pledge is MVR 100.00.'],
        [2000.01, 'Only MVR 2,000.00 is left to raise. Pledge that or less.'],
    ])('%s → %s', (amount, message) => expect(pledgeProblem(amount, LIMITS)).toBe(message));

    it('allows the minimum, the whole remaining amount, and anything between', () => {
        expect(pledgeProblem(100, LIMITS)).toBeNull();
        expect(pledgeProblem(2000, LIMITS)).toBeNull();
        expect(pledgeProblem(1234.56, LIMITS)).toBeNull();
    });

    it('refuses more than the wallet holds', () => {
        expect(pledgeProblem(600, { ...LIMITS, balance: '500.00' })).toBe('Your wallet has MVR 500.00. Top up first, or pledge that or less.');
    });
});

describe('wording', () => {
    it('names the amount on the button', () => {
        expect(pledgeLabel(5000)).toBe('Pledge MVR 5,000');
        expect(pledgeLabel(5000.5)).toBe('Pledge MVR 5,000.50');
        expect(pledgeLabel(null)).toBe('Pledge');
    });

    it('says what happens and that the money is simulated', () => {
        expect(pledgeConsequence('18 Dec 2026')).toContain('comes back to your wallet');
        expect(SIMULATED).toBe('Simulated wallet, no real money.');
    });
});
