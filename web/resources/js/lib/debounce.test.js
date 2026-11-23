import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { debounce, SEARCH_DEBOUNCE_MS } from './debounce';

describe('debounce', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('waits about 300 ms after the last keystroke, then calls once with the last value', () => {
        const fn = vi.fn();
        const search = debounce(fn, SEARCH_DEBOUNCE_MS);

        search('r');
        vi.advanceTimersByTime(200);
        search('re');
        vi.advanceTimersByTime(200);
        search('reef');
        expect(fn).not.toHaveBeenCalled();

        vi.advanceTimersByTime(299);
        expect(fn).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        expect(fn).toHaveBeenCalledTimes(1);
        expect(fn).toHaveBeenCalledWith('reef');
        expect(SEARCH_DEBOUNCE_MS).toBe(300);
    });

    it('can cancel a waiting call and reports whether one is pending', () => {
        const fn = vi.fn();
        const search = debounce(fn, 300);

        search('x');
        expect(search.pending()).toBe(true);
        search.cancel();
        expect(search.pending()).toBe(false);
        vi.advanceTimersByTime(1000);
        expect(fn).not.toHaveBeenCalled();
    });
});
