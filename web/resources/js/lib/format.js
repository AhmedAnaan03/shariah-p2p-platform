/**
 * Formats from the design system (docs/design-system/README.md, "Voice and content"):
 * money `MVR 12,500.00`, compact `MVR 12.5K` (chart axes only), percentages to one decimal
 * `12.4%`, dates `12 Nov 2026`, times `14:30`. Anything that isn't a usable value comes back as
 * an em dash, so a page never shows "NaN" or "Invalid Date".
 *
 * Plain functions with no React, so they are unit-tested on their own (format.test.js).
 */

export const EMPTY = '—';

// A real minus sign (U+2212), the same width as "+" in tabular figures.
const MINUS = '−';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** A finite number from a number or numeric string ("12500.5"), otherwise null. */
export function toNumber(value) {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }
    if (typeof value === 'string' && value.trim() !== '') {
        const number = Number(value.replaceAll(',', ''));
        return Number.isFinite(number) ? number : null;
    }
    return null;
}

/**
 * The figure part of an amount, without the currency code: "12,500.00".
 * `sign: true` adds "+" to positive amounts (received money); negatives always get "−".
 */
export function formatAmount(value, { decimals = 2, sign = false } = {}) {
    const number = toNumber(value);
    if (number === null) {
        return EMPTY;
    }

    const rounded = Number(number.toFixed(decimals));
    const figure = Math.abs(rounded).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });

    if (rounded < 0) {
        return `${MINUS}${figure}`;
    }
    return sign && rounded > 0 ? `+${figure}` : figure;
}

/**
 * What the user has typed into an amount or percentage box, cleaned up as they type: digits and
 * one decimal point, at most `decimals` decimal places; commas, spaces and anything else
 * dropped. "120,000.505" -> "120000.50". Returns a string so a half-typed "120." survives.
 */
export function cleanNumberInput(text, { decimals = 2 } = {}) {
    const kept = String(text ?? '').replace(/[^\d.]/g, '');
    const [whole, ...rest] = kept.split('.');
    if (rest.length === 0 || decimals === 0) {
        return whole;
    }
    return `${whole}.${rest.join('').slice(0, decimals)}`;
}

/** "MVR 12,500.00". For button labels use decimals: 0, e.g. "Pledge MVR 5,000". */
export function formatMoney(value, { code = 'MVR', ...options } = {}) {
    const amount = formatAmount(value, options);
    return amount === EMPTY ? EMPTY : `${code} ${amount}`;
}

/** "MVR 12.5K", "MVR 1.3M": chart axes only, never in running text. */
export function formatCompactMoney(value, { code = 'MVR' } = {}) {
    const number = toNumber(value);
    if (number === null) {
        return EMPTY;
    }

    const abs = Math.abs(number);
    const [divisor, suffix] = abs >= 1e6 ? [1e6, 'M'] : abs >= 1e3 ? [1e3, 'K'] : [1, ''];
    const short = Number((abs / divisor).toFixed(1)).toString();

    return `${code} ${number < 0 ? MINUS : ''}${short}${suffix}`;
}

/** "12.4%". `value` is already a percentage (12.43, not 0.1243). */
export function formatPercent(value, { decimals = 1 } = {}) {
    const number = toNumber(value);
    return number === null ? EMPTY : `${formatAmount(number, { decimals })}%`;
}

/**
 * A Date from a Date, an ISO date-time ("2026-11-12T09:30:00Z") or a plain date
 * ("2026-11-12"). A plain date is read as that calendar day wherever the browser is, so it
 * never shifts to the day before.
 */
export function toDate(value) {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    const plain = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (plain) {
        const [year, month, day] = plain.slice(1).map(Number);
        const date = new Date(year, month - 1, day);
        // new Date() rolls 2026-13-45 over into 2027; a real date gives back its own parts.
        const real = date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
        return real ? date : null;
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

/** "12 Nov 2026". */
export function formatDate(value) {
    const date = toDate(value);
    return date ? `${date.getDate()} ${MONTHS[date.getMonth()]} ${date.getFullYear()}` : EMPTY;
}

/** "14:30" (24-hour). */
export function formatTime(value) {
    const date = toDate(value);
    if (!date) {
        return EMPTY;
    }
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** File size people read: "820 KB", "7.2 MB". */
export function formatBytes(bytes) {
    const number = toNumber(bytes);
    if (number === null || number < 0) {
        return EMPTY;
    }
    if (number < 1024 * 1024) {
        return `${Math.max(1, Math.round(number / 1024))} KB`;
    }
    return `${(number / (1024 * 1024)).toFixed(1)} MB`;
}

/** "Aishath Rasheed" → "AR"; one name → its first letter. For Avatar. */
export function initials(name) {
    const words = String(name ?? '').trim().split(/\s+/).filter(Boolean);
    if (words.length === 0) {
        return '?';
    }
    const letters = words.length === 1 ? words[0][0] : words[0][0] + words[words.length - 1][0];
    return letters.toUpperCase();
}

/**
 * How long is left until a deadline, counted in whole days: "Closes today" on its last day,
 * "1 day left", "12 days left". A passed or unusable deadline gives "Closed" or a dash.
 */
export function daysLeftLabel(deadline, now = new Date()) {
    const end = toDate(deadline);
    if (end === null) {
        return EMPTY;
    }
    const ms = end.getTime() - now.getTime();
    if (ms < 0) {
        return 'Closed';
    }
    const days = Math.ceil(ms / 86_400_000) - 1;
    if (days <= 0) {
        return 'Closes today';
    }
    return days === 1 ? '1 day left' : `${days} days left`;
}
