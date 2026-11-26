import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import ChartFrame from './ChartFrame';

/** PB-34: every chart has a visible title, a takeaway, a legend and "View as table". */
describe('ChartFrame', () => {
    const frame = (
        <ChartFrame title="Capital and markup by month" takeaway="MVR 11,000.00 over 3 months." legend={[{ label: 'Capital', color: 'var(--color-chart-1)' }]} table={{ columns: ['Month', 'Capital'], rows: [['Dec 2026', 'MVR 3,333.33']] }}>
            <svg />
        </ChartFrame>
    );

    it('names the chart by its title and describes it by its takeaway', () => {
        const html = renderToStaticMarkup(frame);
        expect(html).toMatch(/<figure aria-labelledby="[^"]+"/);
        expect(html).toContain('Capital and markup by month');
        expect(html).toContain('role="img" aria-label="MVR 11,000.00 over 3 months."');
    });

    it('has a legend with theme colours and a View as table button', () => {
        const html = renderToStaticMarkup(frame);
        expect(html).toContain('aria-label="Legend"');
        expect(html).toContain('background-color:var(--color-chart-1)');
        expect(html).toContain('View as table');
        expect(html).toContain('aria-pressed="false"');
    });
});
