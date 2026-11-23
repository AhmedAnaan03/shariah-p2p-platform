import { describe, expect, it } from 'vitest';
import { formatMoneyTyping, groupThousands, parseMoney } from './moneyInput';

describe('groupThousands and parseMoney', () => {
    it('groups whole digits in threes and reads the plain number back', () => {
        expect(groupThousands('1234567')).toBe('1,234,567');
        expect(groupThousands('999')).toBe('999');
        expect(parseMoney('1,234,567.50')).toBe(1234567.5);
    });

    it('has no number for blank or a lone point', () => {
        expect(parseMoney('')).toBeNull();
        expect(parseMoney('.')).toBeNull();
        expect(parseMoney('abc')).toBeNull();
    });
});

describe('formatMoneyTyping', () => {
    it('adds separators as you type and keeps the cursor at the end', () => {
        expect(formatMoneyTyping('1234', 4, '123')).toEqual({ text: '1,234', caret: 5, value: 1234 });
        expect(formatMoneyTyping('1,2345', 6, '1,234')).toEqual({ text: '12,345', caret: 6, value: 12345 });
        expect(formatMoneyTyping('120,0000', 8, '120,000')).toEqual({ text: '1,200,000', caret: 9, value: 1200000 });
    });

    it('keeps the cursor after the same digit when typing in the middle', () => {
        // "12,|345" and type 9: the digits are 129345, shown "129,345", cursor after the 9.
        expect(formatMoneyTyping('129,345', 3, '12,345')).toEqual({ text: '129,345', caret: 3, value: 129345 });
        expect(formatMoneyTyping('1,0234', 3, '1,234')).toEqual({ text: '10,234', caret: 2, value: 10234 });
    });

    it('turns Backspace on a separator into deleting the digit before it', () => {
        // "1,|234": Backspace removes the comma, which would change nothing; the 1 goes instead.
        expect(formatMoneyTyping('1234', 1, '1,234')).toEqual({ text: '234', caret: 0, value: 234 });
    });

    it('keeps a decimal point and at most 2 decimals', () => {
        expect(formatMoneyTyping('1,234.', 6, '1,234')).toEqual({ text: '1,234.', caret: 6, value: 1234 });
        expect(formatMoneyTyping('1,234.567', 9, '1,234.56')).toEqual({ text: '1,234.56', caret: 8, value: 1234.56 });
    });

    it('drops letters and other characters, and is empty for blank', () => {
        // The cursor was after "12a": it stays after the 2, in "1,2|34".
        expect(formatMoneyTyping('12a34', 3, '12')).toEqual({ text: '1,234', caret: 3, value: 1234 });
        expect(formatMoneyTyping('', 0, '5')).toEqual({ text: '', caret: 0, value: null });
    });
});
