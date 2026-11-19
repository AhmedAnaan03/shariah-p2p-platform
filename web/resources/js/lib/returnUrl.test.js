import { describe, expect, it } from 'vitest';
import { RETURN_PARAM, withReturn } from './returnUrl';

describe('withReturn', () => {
    it('adds the current page, query and all, as return_to', () => {
        expect(withReturn('/admin/campaigns/7', '/admin/campaigns?tab=live&search=van')).toBe(
            '/admin/campaigns/7?return_to=%2Fadmin%2Fcampaigns%3Ftab%3Dlive%26search%3Dvan',
        );
    });

    it('turns a full URL from the server into a path', () => {
        expect(withReturn('http://127.0.0.1:8000/admin/verifications/3', '/admin/campaigns/7')).toBe(
            '/admin/verifications/3?return_to=%2Fadmin%2Fcampaigns%2F7',
        );
    });

    it('nests: a page opened with a return_to passes its whole address on', () => {
        const review = withReturn('/admin/campaigns/7', '/admin/campaigns?tab=live');
        const record = withReturn('/admin/verifications/3', review);

        const back = new URL(record, 'http://x').searchParams.get(RETURN_PARAM);
        expect(back).toBe(review);
        expect(new URL(back, 'http://x').searchParams.get(RETURN_PARAM)).toBe('/admin/campaigns?tab=live');
    });

    it('leaves the link alone when there is no page to return to', () => {
        expect(withReturn('/borrower/campaigns/2', '')).toBe('/borrower/campaigns/2');
    });
});
