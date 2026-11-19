import { describe, expect, it, vi } from 'vitest';
import { applyTheme, followSystemTheme, normaliseTheme, resolveTheme } from './theme';

function fakeWindow(dark) {
    const listeners = new Set();
    const query = {
        matches: dark,
        addEventListener: (_, fn) => listeners.add(fn),
        removeEventListener: (_, fn) => listeners.delete(fn),
    };
    return {
        matchMedia: vi.fn(() => query),
        setDark(value) {
            query.matches = value;
            listeners.forEach((fn) => fn());
        },
        listeners,
    };
}

const fakeDocument = () => ({ documentElement: { dataset: {} } });

describe('normaliseTheme', () => {
    it('keeps light, dark and system and turns anything else into system', () => {
        expect(normaliseTheme('light')).toBe('light');
        expect(normaliseTheme('dark')).toBe('dark');
        expect(normaliseTheme('system')).toBe('system');
        expect(normaliseTheme('')).toBe('system');
        expect(normaliseTheme('purple')).toBe('system');
        expect(normaliseTheme(undefined)).toBe('system');
    });
});

describe('resolveTheme', () => {
    it('uses the choice, or the device for system', () => {
        expect(resolveTheme('dark', false)).toBe('dark');
        expect(resolveTheme('light', true)).toBe('light');
        expect(resolveTheme('system', true)).toBe('dark');
        expect(resolveTheme('system', false)).toBe('light');
    });
});

describe('applyTheme and followSystemTheme', () => {
    it('writes the choice and the theme to show on <html>', () => {
        const doc = fakeDocument();
        applyTheme('system', doc, fakeWindow(true));
        expect(doc.documentElement.dataset).toEqual({ themePreference: 'system', theme: 'dark' });

        applyTheme('light', doc, fakeWindow(true));
        expect(doc.documentElement.dataset).toEqual({ themePreference: 'light', theme: 'light' });
    });

    it('follows the device only while the choice is system, and stops when asked', () => {
        const doc = fakeDocument();
        const win = fakeWindow(false);
        applyTheme('system', doc, win);

        const stop = followSystemTheme('system', doc, win);
        win.setDark(true);
        expect(doc.documentElement.dataset.theme).toBe('dark');

        stop();
        expect(win.listeners.size).toBe(0);
        expect(followSystemTheme('dark', doc, win)).toBeTypeOf('function');
        expect(win.listeners.size).toBe(0);
    });
});
