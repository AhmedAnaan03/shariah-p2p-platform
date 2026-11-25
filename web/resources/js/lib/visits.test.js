import { describe, expect, it } from 'vitest';
import { closesMenu, isOtherPage } from './visits';

describe('closesMenu', () => {
    it('closes for a link, a filter change or the back button (GET)', () => {
        expect(closesMenu({ method: 'get' })).toBe(true);
        expect(closesMenu({ method: 'get', preserveState: true })).toBe(true);
    });

    it('closes for a form that moves on to another page', () => {
        expect(closesMenu({ method: 'post' })).toBe(true);
        expect(closesMenu({ method: 'put', preserveState: false })).toBe(true);
    });

    it('stays open for a setting saved from the menu (theme, Shariah-only)', () => {
        expect(closesMenu({ method: 'patch', preserveState: true })).toBe(false);
        expect(closesMenu({ method: 'PATCH', preserveState: true })).toBe(false);
    });

    it('closes when the visit is missing or blank', () => {
        expect(closesMenu(undefined)).toBe(true);
        expect(closesMenu({})).toBe(true);
    });
});

describe('isOtherPage', () => {
    it('compares paths only', () => {
        expect(isOtherPage('/investor/campaigns/4', '/investor/campaigns')).toBe(true);
        expect(isOtherPage('/investor/campaigns', '/investor/campaigns?page=2')).toBe(false);
        expect(isOtherPage('/investor', '/investor#main')).toBe(false);
        expect(isOtherPage(undefined, '/')).toBe(false);
    });
});
