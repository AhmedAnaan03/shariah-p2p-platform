import { describe, expect, it } from 'vitest';
import { amountText, nearestStep, positionsFor, rangeText, valuesFor } from './rangeSlider';

const STEPS = [10000, 20000, 50000, 100000, 250000, 500000, 1000000, 2000000];
const LAST = STEPS.length - 1;

describe('nearestStep', () => {
    it('uses the fallback for blank or invalid amounts', () => {
        expect(nearestStep('', STEPS, 0)).toBe(0);
        expect(nearestStep(null, STEPS, LAST)).toBe(LAST);
        expect(nearestStep('abc', STEPS, 3)).toBe(3);
    });

    it('finds an exact step and the closest one otherwise', () => {
        expect(nearestStep(250000, STEPS)).toBe(4);
        expect(nearestStep('50001', STEPS)).toBe(2);
        expect(nearestStep(80000, STEPS)).toBe(3);
    });

    it('stops at the ends for amounts outside the scale', () => {
        expect(nearestStep(5, STEPS)).toBe(0);
        expect(nearestStep(99999999, STEPS)).toBe(LAST);
    });
});

describe('positionsFor and valuesFor', () => {
    it('puts unset ends at the ends of the track', () => {
        expect(positionsFor('', '', STEPS)).toEqual([0, LAST]);
        expect(positionsFor(undefined, 500000, STEPS)).toEqual([0, 5]);
    });

    it('puts a reversed pair the right way round', () => {
        expect(positionsFor(500000, 20000, STEPS)).toEqual([1, 5]);
    });

    it('leaves an end at its limit out of the URL', () => {
        expect(valuesFor([0, LAST], STEPS)).toEqual({ min: '', max: '' });
        expect(valuesFor([2, LAST], STEPS)).toEqual({ min: '50000', max: '' });
        expect(valuesFor([0, 4], STEPS)).toEqual({ min: '', max: '250000' });
        expect(valuesFor([2, 5], STEPS)).toEqual({ min: '50000', max: '500000' });
    });
});

describe('amountText and rangeText', () => {
    it('writes amounts as "MVR 250,000"', () => {
        expect(amountText(250000)).toBe('MVR 250,000');
        expect(amountText(2000000)).toBe('MVR 2,000,000');
    });

    it('sums up the range in words', () => {
        expect(rangeText([0, LAST], STEPS)).toBe('Any amount');
        expect(rangeText([2, LAST], STEPS)).toBe('MVR 50,000 or more');
        expect(rangeText([0, 5], STEPS)).toBe('Up to MVR 500,000');
        expect(rangeText([2, 5], STEPS)).toBe('MVR 50,000 to MVR 500,000');
    });
});
