import { describe, expect, it } from 'vitest';
import { CAMPAIGN_PAGE_TEXT, fundingPercent, investorsText, rateLabel, shariahCampaignText } from './campaigns';
import { SHARIAH_FORBIDDEN_WORDS } from './contracts';

describe('rateLabel', () => {
    it('follows the contract: Interest, Markup, Profit share', () => {
        expect(rateLabel('conventional')).toBe('Interest');
        expect(rateLabel('murabahah')).toBe('Markup');
        expect(rateLabel('mudarabah')).toBe('Profit share');
    });

    it('falls back to a neutral word for a blank or unknown contract', () => {
        expect(rateLabel('')).toBe('Return');
        expect(rateLabel('ijarah')).toBe('Return');
        expect(rateLabel(undefined)).toBe('Return');
    });
});

describe('fundingPercent', () => {
    it('is 0 with nothing pledged, a blank or a zero target', () => {
        expect(fundingPercent(0, 50000)).toBe(0);
        expect(fundingPercent(null, 50000)).toBe(0);
        expect(fundingPercent(1000, 0)).toBe(0);
        expect(fundingPercent(1000, '')).toBe(0);
        expect(fundingPercent('abc', 50000)).toBe(0);
        expect(fundingPercent(-500, 50000)).toBe(0);
    });

    it('rounds down to a whole percentage, so 99.9% never shows as 100%', () => {
        expect(fundingPercent(12500, 50000)).toBe(25);
        expect(fundingPercent(49950, 50000)).toBe(99);
        expect(fundingPercent('25000.00', '50000.00')).toBe(50);
    });

    it('stops at 100', () => {
        expect(fundingPercent(50000, 50000)).toBe(100);
        expect(fundingPercent(60000, 50000)).toBe(100);
    });
});

describe('investorsText', () => {
    it('counts investors in words', () => {
        expect(investorsText(0)).toBe('0 investors');
        expect(investorsText(1)).toBe('1 investor');
        expect(investorsText(12)).toBe('12 investors');
        expect(investorsText(undefined)).toBe('0 investors');
    });
});

describe('Shariah wording on the card and campaign page', () => {
    it('never says "interest" or "guaranteed"', () => {
        for (const text of shariahCampaignText()) {
            for (const word of SHARIAH_FORBIDDEN_WORDS) {
                expect(text.toLowerCase()).not.toMatch(new RegExp(`\\b${word}\\b`));
            }
        }
    });

    it('still warns plainly that money and returns are at risk', () => {
        expect(CAMPAIGN_PAGE_TEXT.riskWarning).toContain('You could lose some or all of your money');
        expect(CAMPAIGN_PAGE_TEXT.riskWarning).toContain('you could receive nothing');
    });
});
