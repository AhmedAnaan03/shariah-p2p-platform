/**
 * Thousands separators while typing (PB-57), without the cursor jumping. MoneyInput calls
 * formatMoneyTyping on every keystroke with what the box now holds and where the cursor is;
 * it gets back the formatted text, where to put the cursor, and the plain number to send.
 *
 *   formatMoneyTyping('12345', 5)  →  { text: '12,345', caret: 6, value: 12345 }
 *
 * The cursor stays after the same digit it was after: the separators are skipped when counting.
 * Deleting a separator (Backspace just after a comma, Delete just before one) deletes the digit
 * next to it instead, so the key never seems to do nothing.
 */
import { cleanNumberInput } from './format';

const SIGNIFICANT = /[\d.]/;

/** "1234567" → "1,234,567". */
export function groupThousands(digits) {
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/** The plain number for "1,234.5", or null when there is none. */
export function parseMoney(text) {
    const clean = cleanNumberInput(text);
    return clean === '' || clean === '.' ? null : Number(clean);
}

/**
 * @param {string} raw         the box's value after the keystroke
 * @param {number} caret       the cursor position in raw
 * @param {string} [previous]  the formatted value before the keystroke
 * @returns {{ text: string, caret: number, value: number|null }}
 */
export function formatMoneyTyping(raw, caret, previous = '') {
    let input = String(raw ?? '');
    let at = Math.max(0, Math.min(caret ?? input.length, input.length));

    // A separator was deleted: the digits are unchanged and the text is one shorter.
    const removedOneCharacter = input.length === previous.length - 1;
    if (removedOneCharacter && cleanNumberInput(input) === cleanNumberInput(previous) && previous[at] === ',') {
        // Backspace after "1,|234" leaves "1|234": remove the digit before the cursor.
        if (at > 0) {
            input = input.slice(0, at - 1) + input.slice(at);
            at -= 1;
        }
    }

    const clean = cleanNumberInput(input);
    const [whole, decimals] = clean.split('.');
    const text = groupThousands(whole) + (clean.includes('.') ? `.${decimals ?? ''}` : '');

    // How many digits (and the point) were before the cursor, ignoring separators and anything
    // the cleaning dropped, then the same position in the formatted text.
    const wanted = Math.min(cleanNumberInput(input.slice(0, at)).length, clean.length);
    let position = 0;
    let seen = 0;
    while (position < text.length && seen < wanted) {
        if (SIGNIFICANT.test(text[position])) {
            seen += 1;
        }
        position += 1;
    }

    return { text, caret: position, value: parseMoney(clean) };
}
