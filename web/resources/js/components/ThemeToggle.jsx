import { router, usePage } from '@inertiajs/react';
import * as RadixRadio from '@radix-ui/react-radio-group';
import { useEffect, useId, useState } from 'react';
import cx from '../lib/cx';
import { applyTheme, normaliseTheme } from '../lib/theme';
import Icon from './Icon';
import Tooltip from './Tooltip';

const OPTIONS = [
    { value: 'light', label: 'Light', icon: 'sun' },
    { value: 'dark', label: 'Dark', icon: 'moon' },
    { value: 'system', label: 'System', icon: 'desktop' },
];

/**
 * Light, dark or system theme (PB-55), at the bottom of the sidebar and in the mobile menu.
 *
 *   <ThemeToggle />                 // three labelled options side by side
 *   <ThemeToggle compact />         // the collapsed sidebar: icons stacked, labels as tooltips
 *   <ThemeToggle save={false} />    // the design preview: switches the page without saving
 *
 * A Radix radio group: one tab stop, arrow keys change the choice, and each option's name is
 * read out. The colours change at once (with the palette's cross-fade, instant with reduced
 * motion); the choice is then saved for the signed-in user with a PATCH, so the server paints
 * every later page in it. It works with either mode: the theme and the mode are separate
 * attributes on <html>, giving all four palettes.
 */
export default function ThemeToggle({ compact = false, save = true }) {
    const { theme } = usePage().props;
    const [value, setValue] = useState(() => normaliseTheme(theme));
    // The sidebar and the mobile menu can both be in the page, so the label id must be unique.
    const labelId = useId();

    // Follow the saved choice if it changes elsewhere (the other toggle, another tab's reload).
    useEffect(() => setValue(normaliseTheme(theme)), [theme]);

    function change(next) {
        setValue(next);
        applyTheme(next);
        if (!save) {
            return; // the design preview: no signed-in user to save it for
        }
        router.patch('/account/theme', { theme: next }, { preserveState: true, preserveScroll: true, only: ['theme'] });
    }

    return (
        <div className={cx(compact ? 'flex flex-col items-center gap-1' : 'space-y-2')}>
            {/* On the patterned band, so text-secondary, never text-muted (CLAUDE.md). */}
            <p id={labelId} className={compact ? 'sr-only' : 'px-3 text-overline text-text-secondary'}>
                Theme
            </p>
            <RadixRadio.Root
                aria-labelledby={labelId}
                value={value}
                onValueChange={change}
                orientation={compact ? 'vertical' : 'horizontal'}
                className={cx('flex gap-1 rounded-md border border-border bg-surface p-1', compact ? 'flex-col' : 'w-full')}
            >
                {OPTIONS.map((option) => {
                    const item = (
                        <RadixRadio.Item
                            key={option.value}
                            value={option.value}
                            aria-label={compact ? option.label : undefined}
                            className={cx(
                                'flex h-8 items-center justify-center gap-1.5 rounded-sm text-body-sm font-medium text-text-secondary transition-colors duration-(--duration-fast) ease-standard hover:text-text pointer-coarse:min-h-(--touch-target)',
                                'data-[state=checked]:bg-primary-subtle data-[state=checked]:text-primary-ink',
                                compact ? 'w-8' : 'flex-1 px-2',
                            )}
                        >
                            <Icon name={option.icon} size={16} weight={value === option.value ? 'bold' : 'regular'} />
                            {!compact && option.label}
                        </RadixRadio.Item>
                    );

                    return compact ? (
                        <Tooltip key={option.value} content={option.label} side="right">
                            {item}
                        </Tooltip>
                    ) : (
                        item
                    );
                })}
            </RadixRadio.Root>
        </div>
    );
}
