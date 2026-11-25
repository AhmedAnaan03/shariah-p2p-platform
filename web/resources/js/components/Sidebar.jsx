import { Link } from '@inertiajs/react';
import { motion } from 'motion/react';
import cx from '../lib/cx';
import { duration, ease, useReducedMotion } from '../lib/motion';
import BackgroundPattern from './BackgroundPattern';
import Icon from './Icon';
import Tooltip from './Tooltip';
import Wordmark from './Wordmark';

// Text that fades out while the sidebar narrows (and in while it widens), over the same 250 ms
// as the width. With reduced motion it changes at once.
const FADE = 'transition-opacity duration-(--duration-moderate) ease-standard motion-reduce:transition-none';

/**
 * The portal navigation (docs/design-system/components/Sidebar.md): a light, patterned panel
 * with the wordmark, nav groups, the active page and, at the bottom, the theme toggle and the
 * collapse button.
 *
 *   <Sidebar portal="Investor" groups={[{ items: [{ href, icon, label, active }] }]} collapsed={false} footer={<ThemeToggle />} onToggleCollapse={...} />
 *
 * 264 px wide, 72 px collapsed; the width itself is animated by PortalLayout's <aside>.
 * Collapsing never moves anything up or down and nothing reflows while the width changes:
 *
 * - Every row keeps its height in both states. Text that only makes sense expanded (the portal
 *   name, group labels, item labels, the full wordmark) stays in place, on one line, and fades
 *   out while the narrowing panel clips it.
 * - Icons sit at the same left offset in both states (12 px nav padding + 13 px inside the item
 *   puts a 20 px icon in the middle of the 72 px rail), so they never move at all.
 * - The bottom row is the theme toggle (left) and the icon-only collapse button (right). In 72 px
 *   they don't fit side by side, so they stack; Motion layout animations move both to their new
 *   places over duration-moderate with ease-standard, and the collapse icon turns 180°. Both rows
 *   are laid out from the left edge at a fixed width, so the positions Motion measures don't
 *   depend on the width mid-animation.
 * - With reduced motion all of it is instant.
 *
 * Collapsed, item labels stay in the links for screen readers and also show as tooltips. In the
 * mobile drawer (`collapsible={false}`) the footer is a plain stack.
 */
export default function Sidebar({ portal, groups = [], collapsed = false, footer, onNavigate, collapsible = true, onToggleCollapse }) {
    const reduced = useReducedMotion();
    const move = reduced ? { duration: 0 } : { duration: duration.moderate, ease: ease.standard };
    // With reduced motion, no layout animation at all: the controls are simply in their new place.
    const layout = reduced ? false : 'position';
    const hide = collapsed ? 'opacity-0' : 'opacity-100';
    const show = collapsed ? 'opacity-100' : 'opacity-0';

    return (
        <div className="relative flex h-full flex-col overflow-hidden border-r border-border bg-band">
            <BackgroundPattern fade="down" />

            <div className="relative flex h-(--topbar) shrink-0 items-center px-6">
                {/* Named by the wordmark's own text plus ", home" (so the visible name is part of the
                    accessible one), in both states: collapsed, the faded wordmark is still read. */}
                <Link href="/" className="relative rounded-sm">
                    <Wordmark size="lg" className={cx('block whitespace-nowrap', FADE, hide)} />
                    <span className="sr-only">, home</span>
                    {/* The monogram in the rail: its "A" sits exactly on the wordmark's "A". */}
                    <span aria-hidden="true" className={cx('absolute inset-y-0 left-0 flex items-center font-display text-h2 font-bold tracking-tight text-text', FADE, show)}>
                        A<span className="text-primary">.</span>
                    </span>
                </Link>
            </div>

            {/* Text on the patterned band uses text-secondary: text-muted drops below 4.5:1 where a pattern line runs under it. */}
            {portal && (
                <p aria-hidden={collapsed || undefined} className={cx('relative px-6 pb-2 text-overline whitespace-nowrap text-text-secondary', FADE, hide)}>
                    {portal} portal
                </p>
            )}

            <nav aria-label={`${portal} portal`} className="relative flex-1 overflow-x-hidden overflow-y-auto px-3 pb-4">
                {groups.map((group, index) => (
                    <div key={group.label ?? index} className="mt-2 first:mt-0">
                        {group.label && (
                            <p aria-hidden={collapsed || undefined} className={cx('px-3.25 pt-4 pb-2 text-overline whitespace-nowrap text-text-secondary', FADE, hide)}>
                                {group.label}
                            </p>
                        )}
                        <ul className="space-y-1">
                            {group.items.map((item) => (
                                <li key={item.href}>
                                    <NavItem item={item} collapsed={collapsed} onNavigate={onNavigate} hide={hide} />
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </nav>

            {collapsible ? (
                <div className="relative shrink-0">
                    {/* The row's top border moves with the row (taller when stacked). */}
                    <motion.div layout={layout} transition={move} aria-hidden="true" className="absolute inset-x-0 top-0 h-px bg-border" />
                    <div className={collapsed ? 'flex w-(--sidebar-collapsed) flex-col items-center gap-3 py-4' : 'flex w-(--sidebar) items-center justify-between px-4 py-4'}>
                        {footer && (
                            <motion.div layout={layout} transition={move} className="flex">
                                {footer}
                            </motion.div>
                        )}
                        <Tooltip content={collapsed ? 'Expand sidebar' : 'Collapse sidebar'} side={collapsed ? 'right' : 'top'}>
                            <motion.button
                                layout={layout}
                                transition={move}
                                type="button"
                                onClick={onToggleCollapse}
                                aria-expanded={!collapsed}
                                aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                                className="flex size-10 items-center justify-center rounded-md text-icon hover:bg-band-hover hover:text-text pointer-coarse:size-(--touch-target)"
                            >
                                <motion.span initial={false} animate={{ rotate: collapsed ? 180 : 0 }} transition={move} className="flex">
                                    <Icon name="caret-double-left" />
                                </motion.span>
                            </motion.button>
                        </Tooltip>
                    </div>
                </div>
            ) : (
                footer && <div className="relative space-y-3 border-t border-border px-4 py-4">{footer}</div>
            )}
        </div>
    );
}

function NavItem({ item, collapsed, onNavigate, hide }) {
    return (
        // Always wrapped, so expanding and collapsing never re-creates the link (which would
        // restart its fade); the tooltip only opens while collapsed.
        <Tooltip content={item.label} side="right" disabled={!collapsed}>
            <Link
                href={item.href}
                onClick={() => onNavigate?.(item)}
                aria-current={item.active ? 'page' : undefined}
                className={cx(
                    'relative flex h-10 items-center gap-3 rounded-md border pr-3 pl-3.25 text-body font-medium transition-colors duration-(--duration-fast) ease-standard pointer-coarse:min-h-(--touch-target)',
                    item.active
                        ? 'border-border bg-nav-active text-nav-active-ink shadow-xs'
                        : 'border-transparent text-text-secondary hover:bg-band-hover hover:text-text',
                )}
            >
                {item.active && <span aria-hidden="true" className="absolute inset-y-2 -left-px w-(--nav-indicator) rounded-r-sm bg-primary" />}
                <Icon name={item.icon} weight={item.active ? 'bold' : 'regular'} className={cx('shrink-0', item.active ? 'text-primary' : 'text-icon')} />
                {/* Collapsed, the label is invisible but still in the link, so screen readers read
                    the same name either way. */}
                <span className={cx('min-w-0 overflow-hidden whitespace-nowrap', FADE, hide)}>{item.label}</span>
                {item.count !== undefined && (
                    <span className={cx('ml-auto shrink-0 rounded-sm bg-primary-muted px-1.5 font-mono text-code text-primary-ink', FADE, hide)}>{item.count}</span>
                )}
            </Link>
        </Tooltip>
    );
}
