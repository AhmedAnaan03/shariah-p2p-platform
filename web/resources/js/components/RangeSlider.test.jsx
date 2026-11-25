import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import RangeSlider from './RangeSlider';

const STEPS = [10000, 20000, 50000, 100000, 250000, 500000, 2000000];

/** PB-16 review: the amount slider's two handles are named and read their amount in MVR. */
describe('RangeSlider', () => {
    it('names both handles and reads their amounts in MVR', () => {
        const html = renderToStaticMarkup(<RangeSlider label="Amount" steps={STEPS} value={[2, 4]} onCommit={() => {}} />);
        expect(html).toContain('aria-label="Minimum amount"');
        expect(html).toContain('aria-label="Maximum amount"');
        expect(html).toContain('aria-valuetext="MVR 50,000"');
        expect(html).toContain('aria-valuetext="MVR 250,000"');
        expect(html).toContain('MVR 50,000 to MVR 250,000');
        expect(html).toContain('Reset');
    });

    it('shows "Any amount" and no Reset at the ends of the track', () => {
        const html = renderToStaticMarkup(<RangeSlider label="Amount" steps={STEPS} value={[0, 6]} onCommit={() => {}} />);
        expect(html).toContain('Any amount');
        expect(html).not.toContain('Reset');
    });
});
