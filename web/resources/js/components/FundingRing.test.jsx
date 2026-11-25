import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import FundingRing from './FundingRing';

/**
 * PB-17 fix: the details beside the ring used to sit in a flex item with no width of its own,
 * so on a narrow card they collapsed and the text ran one letter per line. These render the
 * component; scripts/screenshots.mjs checks the real layout at 390, 820 and 1440 px.
 */
describe('FundingRing', () => {
    it('shows the percentage and reads it out', () => {
        const html = renderToStaticMarkup(<FundingRing value={30000} max={120000} />);
        expect(html).toContain('role="progressbar"');
        expect(html).toContain('aria-valuenow="25"');
        expect(html).toContain('aria-label="Funding: 25% funded"');
        expect(html).toContain('>25%<');
    });

    it('is 0% with nothing raised, a zero target or a blank value', () => {
        expect(renderToStaticMarkup(<FundingRing value={0} max={120000} />)).toContain('aria-valuenow="0"');
        expect(renderToStaticMarkup(<FundingRing value={5000} max={0} />)).toContain('aria-valuenow="0"');
        expect(renderToStaticMarkup(<FundingRing value={undefined} max={120000} />)).toContain('aria-valuenow="0"');
    });

    it('stops at 100%', () => {
        expect(renderToStaticMarkup(<FundingRing value={150000} max={120000} />)).toContain('aria-valuenow="100"');
    });

    it('keeps the centre text on one line each', () => {
        const html = renderToStaticMarkup(<FundingRing value={0} max={120000} />);
        expect(html).toMatch(/class="[^"]*whitespace-nowrap[^"]*"/);
    });

    it('gives the details beside the ring a real width, so they wrap under it on narrow screens instead of collapsing', () => {
        const html = renderToStaticMarkup(
            <FundingRing value={0} max={120000}>
                <p>MVR 120,000.00 target</p>
            </FundingRing>,
        );
        expect(html).toMatch(/class="[^"]*flex-wrap[^"]*"/);
        expect(html).toMatch(/<div data-funding-details="true" class="min-w-0 flex-1 basis-40"><p>MVR 120,000.00 target<\/p><\/div>/);
    });
});
