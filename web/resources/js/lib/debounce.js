/**
 * Calls `fn` once calls have stopped for `wait` ms (PB-54: search as you type without a
 * request per key). `cancel()` drops a pending call; `pending()` says whether one is waiting.
 *
 *   const search = debounce((text) => reload({ search: text }), SEARCH_DEBOUNCE_MS);
 */
export const SEARCH_DEBOUNCE_MS = 300;

export function debounce(fn, wait) {
    let timer;

    function debounced(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => {
            timer = undefined;
            fn(...args);
        }, wait);
    }

    debounced.cancel = () => {
        clearTimeout(timer);
        timer = undefined;
    };
    debounced.pending = () => timer !== undefined;

    return debounced;
}
