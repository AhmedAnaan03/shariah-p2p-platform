import { useLayoutEffect } from 'react';
import { normaliseMode, writeDocumentMode } from '../lib/mode';

/**
 * Puts the app in conventional or Shariah mode (docs/design-system/components/ModeScope.md).
 *
 * Used once, at the root (AppRoot): it keeps <html data-mode> in step with the `mode` prop the
 * server shares, and puts it back on every page change (`pageKey`, the page URL). The theme's CSS then re-points every colour token, and the @property
 * registrations make the browser cross-fade them over 500 ms (instant with reduced motion).
 *
 * With `as`, it instead renders that element with its own data-mode / data-theme. That is only
 * for the design preview page; don't nest a second mode in the app to tint one card.
 */
export default function ModeScope({ mode, appearance, as, pageKey, className, style, children }) {
    const value = normaliseMode(mode);

    // Re-applied on every page change (`pageKey`), not only when the mode changes: a page may
    // have previewed another mode (the borrower's request form follows the chosen contract,
    // PB-13), and the server's mode must win again on the next page.
    useLayoutEffect(() => {
        if (as) {
            return;
        }
        writeDocumentMode(value);
        if (appearance) {
            document.documentElement.dataset.theme = appearance;
        }
    }, [as, value, appearance, pageKey]);

    if (as) {
        const Element = as;
        return (
            <Element data-mode={value} data-theme={appearance ?? 'light'} className={className} style={style}>
                {children}
            </Element>
        );
    }

    return children;
}
