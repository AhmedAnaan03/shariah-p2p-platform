import { router } from '@inertiajs/react';
import cx from '../lib/cx';
import { applyTheme, systemTheme, useDocumentTheme } from '../lib/theme';
import Icon from './Icon';

/**
 * The light / dark switch (PB-55; a minimal icon-only sliding switch since PB-57), at the bottom
 * of the sidebar and in the mobile menu.
 *
 *   <ThemeToggle />              horizontal: the sun on the left, the moon on the right
 *   <ThemeToggle vertical />     the collapsed sidebar, 72 px wide: sun at the top, moon below
 *   <ThemeToggle save={false} /> the design preview: switches the page without saving
 *
 * A button with role="switch", named "Dark theme" and aria-checked when dark, so screen readers
 * say "Dark theme, switch, on". The knob slides over the sun or moon in duration-base with
 * ease-standard; with reduced motion it jumps.
 *
 * It shows the theme on screen now, which may follow the device (lib/theme.js). Using it makes a
 * new choice, saved with the device's setting at that moment: the choice then holds until the
 * device's setting changes ("the most recent change wins").
 */
export default function ThemeToggle({ vertical = false, save = true }) {
    const dark = useDocumentTheme() === 'dark';

    function toggle() {
        const next = dark ? 'light' : 'dark';
        applyTheme(next);
        if (save) {
            router.patch('/account/theme', { theme: next, system: systemTheme() }, { preserveState: true, preserveScroll: true, only: ['theme'] });
        }
    }

    return (
        <button
            type="button"
            role="switch"
            aria-checked={dark}
            aria-label="Dark theme"
            onClick={toggle}
            className={cx(
                'relative flex shrink-0 rounded-full border border-border-control bg-surface p-0.75 transition-colors duration-(--duration-fast) ease-standard hover:border-border-strong',
                vertical ? 'h-14 w-8 flex-col' : 'h-8 w-14',
            )}
        >
            {/* The two ends of the track: what the switch turns on. */}
            <span aria-hidden="true" className={cx('absolute flex items-center justify-center text-icon', vertical ? 'inset-x-0 top-1.5' : 'inset-y-0 left-1.5')}>
                <Icon name="sun" size={16} />
            </span>
            <span aria-hidden="true" className={cx('absolute flex items-center justify-center text-icon', vertical ? 'inset-x-0 bottom-1.5' : 'inset-y-0 right-1.5')}>
                <Icon name="moon" size={16} />
            </span>
            <span
                aria-hidden="true"
                className={cx(
                    'relative flex size-6 items-center justify-center rounded-full bg-primary text-primary-fg shadow-xs transition-transform duration-(--duration-base) ease-standard motion-reduce:transition-none',
                    dark && (vertical ? 'translate-y-6' : 'translate-x-6'),
                )}
            >
                <Icon name={dark ? 'moon' : 'sun'} size={16} weight="bold" />
            </span>
        </button>
    );
}
