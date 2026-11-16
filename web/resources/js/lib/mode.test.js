import { describe, expect, it } from 'vitest';
import { normaliseMode, readDocumentMode, writeDocumentMode } from './mode';

// A stand-in for `document` with just the part mode.js touches.
const fakeDocument = (mode) => ({ documentElement: { dataset: mode === undefined ? {} : { mode } } });

describe('normaliseMode', () => {
    it.each([
        [undefined, 'conventional'],
        ['', 'conventional'],
        ['Shariah', 'conventional'],
        ['islamic', 'conventional'],
        ['conventional', 'conventional'],
        ['shariah', 'shariah'],
    ])('%j → %s', (input, expected) => expect(normaliseMode(input)).toBe(expected));
});

describe('data-mode on <html>', () => {
    it('reads conventional when the attribute is missing', () => {
        expect(readDocumentMode(fakeDocument())).toBe('conventional');
    });

    it('reads the mode the server wrote', () => {
        expect(readDocumentMode(fakeDocument('shariah'))).toBe('shariah');
    });

    it('writes a valid mode', () => {
        const doc = fakeDocument('conventional');
        writeDocumentMode('shariah', doc);
        expect(doc.documentElement.dataset.mode).toBe('shariah');
    });

    it('never writes an unknown mode', () => {
        const doc = fakeDocument('shariah');
        writeDocumentMode('purple', doc);
        expect(doc.documentElement.dataset.mode).toBe('conventional');
    });
});
