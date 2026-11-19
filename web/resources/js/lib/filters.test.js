import { describe, expect, it } from 'vitest';
import { filterParams, hasActiveFilters } from './filters';

describe('filterParams', () => {
    it('keeps other parameters, such as the open tab', () => {
        expect(filterParams('?tab=live', { search: 'van' })).toEqual({ tab: 'live', search: 'van' });
    });

    it('drops blank, "all" and whitespace-only values', () => {
        expect(filterParams('?search=old&status=live', { search: '   ', status: 'all', contract: '' })).toEqual({});
    });

    it('trims the search text and replaces an earlier value', () => {
        expect(filterParams('?status=live', { status: 'rejected', search: '  reef  ' })).toEqual({ status: 'rejected', search: 'reef' });
    });
});

describe('hasActiveFilters', () => {
    it('is true only when one of the bar’s filters is set', () => {
        expect(hasActiveFilters({ tab: 'live' }, ['search', 'status'])).toBe(false);
        expect(hasActiveFilters({ status: 'live' }, ['search', 'status'])).toBe(true);
        expect(hasActiveFilters({}, ['search'])).toBe(false);
    });
});
