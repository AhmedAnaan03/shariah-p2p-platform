import { describe, expect, it } from 'vitest';
import { DEMO_CONTROLS, navigationFor } from './navigation';

/** PB-34: the demo tool's link appears only for an administrator when the server says it exists. */
const hrefs = (navigation) => navigation.groups.flatMap((group) => group.items.map((item) => item.href));

describe('navigationFor', () => {
    it('has no demo link by default, even for an administrator', () => {
        expect(hrefs(navigationFor('admin', '/admin'))).not.toContain(DEMO_CONTROLS.href);
    });

    it('adds Demo controls for an administrator when the demo tools exist', () => {
        const navigation = navigationFor('admin', '/admin/demo', { demoTools: true });
        expect(hrefs(navigation)).toContain('/admin/demo');
        expect(navigation.groups.at(-1).items[0]).toMatchObject({ label: 'Demo controls', active: true });
    });

    it('never adds it for another role', () => {
        expect(hrefs(navigationFor('investor', '/investor', { demoTools: true }))).not.toContain(DEMO_CONTROLS.href);
        expect(hrefs(navigationFor('borrower', '/borrower', { demoTools: true }))).not.toContain(DEMO_CONTROLS.href);
    });
});
