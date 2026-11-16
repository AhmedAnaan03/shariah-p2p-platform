import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { duration, ease, pageTransition, pressProps, rowExit } from './motion';

const css = readFileSync(fileURLToPath(new URL('../../css/tailwind-theme.css', import.meta.url)), 'utf8');
const token = (name) => css.match(new RegExp(`--${name}:\\s*([^;]+);`))[1].trim();

describe('motion tokens match tailwind-theme.css', () => {
    it.each(Object.keys(duration))('duration-%s', (name) => {
        expect(`${Math.round(duration[name] * 1000)}ms`).toBe(token(`duration-${name}`));
    });

    it.each(Object.keys(ease))('ease-%s', (name) => {
        expect(`cubic-bezier(${ease[name].join(', ')})`).toBe(token(`ease-${name}`));
    });
});

describe('reduced motion', () => {
    it('the page change only fades, for 100 ms or less', () => {
        const t = pageTransition(true);
        expect(t.initial).toEqual({ opacity: 0 });
        expect(t.animate.transition.duration).toBeLessThanOrEqual(0.1);
        expect(t.exit.transition.duration).toBeLessThanOrEqual(0.1);
    });

    it('without reduced motion the page rises 8 px over 250 ms', () => {
        const t = pageTransition(false);
        expect(t.initial.y).toBe(8);
        expect(t.animate.transition.duration).toBe(0.25);
    });

    it('a removed row keeps its height with reduced motion', () => {
        expect(rowExit(true)).not.toHaveProperty('height');
        expect(rowExit(false).height).toBe(0);
    });

    it('buttons do not scale with reduced motion or when disabled', () => {
        expect(pressProps(true)).toEqual({});
        expect(pressProps(false, true)).toEqual({});
        expect(pressProps(false).whileTap).toEqual({ scale: 0.98 });
    });
});
