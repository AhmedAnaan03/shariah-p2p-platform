import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import RepaymentTimeline from './RepaymentTimeline';

/** PB-34: the schedule timeline shows each row's label, its state in words and its amount. */
const items = Array.from({ length: 8 }, (_, i) => ({
    id: i + 1,
    label: `Instalment ${i + 1}`,
    state: i < 3 ? 'paid' : 'upcoming',
    stateLabel: i < 3 ? 'Paid' : `Due ${i + 1} Jan`,
    amount: '1000.00',
}));

describe('RepaymentTimeline', () => {
    it('lists rows as an ordered list with their words and amounts', () => {
        const html = renderToStaticMarkup(<RepaymentTimeline items={items.slice(0, 2)} label="Repayment schedule" />);
        expect(html).toContain('<ol aria-label="Repayment schedule"');
        expect(html).toContain('Instalment 1');
        expect(html).toContain('Paid');
        expect(html).toContain('1,000.00');
        expect(html).not.toContain('Show all');
    });

    it('starts a long schedule just before the next unpaid row, with Show all', () => {
        const html = renderToStaticMarkup(<RepaymentTimeline items={items} limit={4} />);
        expect(html).not.toContain('Instalment 2<');
        expect(html).toContain('Instalment 3');
        expect(html).toContain('Instalment 6');
        expect(html).not.toContain('Instalment 7');
        expect(html).toContain('Show all 8');
    });

    it('shows a Mudarabah row awaiting the business report in words, not as late', () => {
        const html = renderToStaticMarkup(
            <RepaymentTimeline items={[{ id: 1, label: 'Planned capital return 1', state: 'awaiting', stateLabel: 'Planned date passed, awaiting business report', amount: '5000.00' }]} />,
        );
        expect(html).toContain('Planned date passed, awaiting business report');
        expect(html).toContain('text-info');
        expect(html).not.toContain('text-danger');
    });
});
