import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import StatusBadge from './StatusBadge';

/** PB-19, PB-20: an investor's campaign page names each pledge's status and where the campaign stands. */
describe('StatusBadge', () => {
    it.each([
        ['held', 'Held in escrow'],
        ['refunded', 'Refunded'],
        ['settled', 'Settled'],
        ['funded', 'Funded'],
        ['failed', 'Failed'],
        ['closed', 'Funding closed'],
        ['repaying', 'Repaying'],
        ['completed', 'Fully repaid'],
        ['defaulted', 'Defaulted'],
    ])('labels "%s" as "%s"', (status, label) => {
        expect(renderToStaticMarkup(<StatusBadge status={status} />)).toContain(label);
    });

    it('falls back to the raw status for one it does not know', () => {
        expect(renderToStaticMarkup(<StatusBadge status="unknown" />)).toContain('unknown');
    });
});
