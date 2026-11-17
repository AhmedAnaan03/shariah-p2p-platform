import { describe, expect, it } from 'vitest';
import {
    CONTRACTS,
    contractLabel,
    fillLimits,
    isShariahContract,
    modeForContract,
    SHARIAH_FORBIDDEN_WORDS,
    shariahScreenText,
    TERM_FIELDS,
} from './contracts';

describe('contract types', () => {
    it('offers exactly conventional, Murabahah and Mudarabah', () => {
        expect(CONTRACTS.map((c) => c.value)).toEqual(['conventional', 'murabahah', 'mudarabah']);
    });

    it('marks only Murabahah and Mudarabah as Shariah', () => {
        expect(CONTRACTS.filter((c) => c.shariah).map((c) => c.value)).toEqual(['murabahah', 'mudarabah']);
        expect(isShariahContract('murabahah')).toBe(true);
        expect(isShariahContract('mudarabah')).toBe(true);
        expect(isShariahContract('conventional')).toBe(false);
        expect(isShariahContract('')).toBe(false);
        expect(isShariahContract(undefined)).toBe(false);
    });

    it('puts the page in Shariah mode for a Shariah contract, conventional otherwise', () => {
        expect(modeForContract('murabahah')).toBe('shariah');
        expect(modeForContract('mudarabah')).toBe('shariah');
        expect(modeForContract('conventional')).toBe('conventional');
        expect(modeForContract('')).toBe('conventional');
    });

    it('labels each contract', () => {
        expect(contractLabel('murabahah')).toBe('Murabahah');
        expect(contractLabel('unknown')).toBe('unknown');
    });
});

describe('Shariah wording', () => {
    it.each(SHARIAH_FORBIDDEN_WORDS)('never says "%s" on a Shariah screen', (word) => {
        for (const text of shariahScreenText()) {
            expect(text.toLowerCase()).not.toContain(word);
        }
    });

    it('checks every Shariah term field, and the conventional rate field is the only one that says interest', () => {
        const all = Object.values(TERM_FIELDS).flatMap((fields) => Object.values(fields));
        const sayingInterest = all.filter((field) => `${field.label} ${field.hint ?? ''}`.toLowerCase().includes('interest'));
        expect(sayingInterest).toEqual([TERM_FIELDS.conventional.interest_rate_percent]);
    });

    it('has no Shariah term field for an interest rate', () => {
        expect(Object.keys(TERM_FIELDS.murabahah)).not.toContain('interest_rate_percent');
        expect(Object.keys(TERM_FIELDS.mudarabah)).not.toContain('interest_rate_percent');
    });
});

describe('fillLimits', () => {
    it('puts the server limits into a hint', () => {
        expect(fillLimits('From {minProfitShare}% to {maxProfitShare}%.', { minProfitShare: 1, maxProfitShare: 99 })).toBe('From 1% to 99%.');
    });

    it('leaves unknown placeholders as they are', () => {
        expect(fillLimits('Up to {missing}.', {})).toBe('Up to {missing}.');
    });
});
