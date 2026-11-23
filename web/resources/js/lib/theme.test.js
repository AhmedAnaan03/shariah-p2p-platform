import { describe, expect, it, vi } from 'vitest';
import { applyTheme, onSystemThemeChange, resolveTheme, systemTheme } from './theme';

function fakeWindow(dark) {
    const listeners = new Set();
    const query = {
        get matches() {
            return dark;
        },
        addEventListener: (_, fn) => listeners.add(fn),
        removeEventListener: (_, fn) => listeners.delete(fn),
    };
    return {
        matchMedia: vi.fn(() => query),
        setDark(value) {
            dark = value;
            listeners.forEach((fn) => fn());
        },
        listeners,
    };
}

describe('resolveTheme: the most recent change wins', () => {
    it('follows the device when nothing has been chosen (blank)', () => {
        expect(resolveTheme({ choice: null, systemAtChoice: null }, 'dark')).toBe('dark');
        expect(resolveTheme(undefined, 'light')).toBe('light');
    });

    it('keeps the choice while the device still has the setting it had then', () => {
        expect(resolveTheme({ choice: 'dark', systemAtChoice: 'light' }, 'light')).toBe('dark');
        expect(resolveTheme({ choice: 'light', systemAtChoice: 'dark' }, 'dark')).toBe('light');
    });

    it('follows the device once its setting has changed since the choice', () => {
        expect(resolveTheme({ choice: 'light', systemAtChoice: 'light' }, 'dark')).toBe('dark');
        expect(resolveTheme({ choice: 'dark', systemAtChoice: 'dark' }, 'light')).toBe('light');
    });

    it('keeps an older choice saved without a device setting, and ignores invalid values', () => {
        expect(resolveTheme({ choice: 'dark', systemAtChoice: null }, 'light')).toBe('dark');
        expect(resolveTheme({ choice: 'purple', systemAtChoice: 'light' }, 'dark')).toBe('dark');
    });
});

describe('systemTheme, applyTheme and onSystemThemeChange', () => {
    it('reads the device, writes <html>, and reports device changes until stopped', () => {
        const win = fakeWindow(false);
        const doc = { documentElement: { dataset: {} } };
        expect(systemTheme(win)).toBe('light');

        applyTheme('dark', doc);
        expect(doc.documentElement.dataset.theme).toBe('dark');
        applyTheme('purple', doc);
        expect(doc.documentElement.dataset.theme).toBe('dark');

        const seen = [];
        const stop = onSystemThemeChange((theme) => seen.push(theme), win);
        win.setDark(true);
        expect(seen).toEqual(['dark']);
        stop();
        expect(win.listeners.size).toBe(0);
    });
});
