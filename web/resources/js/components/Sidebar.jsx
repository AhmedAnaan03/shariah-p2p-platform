import { Link } from '@inertiajs/react';
import cx from '../lib/cx';
import BackgroundPattern from './BackgroundPattern';
import Icon from './Icon';
import Tooltip from './Tooltip';
import Wordmark from './Wordmark';

/**
 * The portal navigation (docs/design-system/components/Sidebar.md): a light, patterned panel
 * with the wordmark, nav groups, the active page and a collapse control.
 *
 *   <Sidebar portal="Investor" groups={[{ items: [{ href, icon, label, active }] }]} collapsed={false} onToggleCollapse={...} />
 *
 * 264 px wide, 72 px collapsed (labels hide, titles become tooltips). Items are 40 px; the
 * active one is a white tile with a border, a bold primary icon and a 3 px primary bar on its
 * left edge, and is marked aria-current="page". It never animates on page change.
 * `footer` sits above the collapse control (the mode switch in the mobile drawer).
 */
export default function Sidebar({ portal, groups = [], collapsed = false, footer, onNavigate, collapsible = true, onToggleCollapse }) {
    return (
        <div className="relative flex h-full flex-col border-r border-border bg-band">
            <BackgroundPattern fade="down" />

            <div className={cx('relative flex h-(--topbar) shrink-0 items-center', collapsed ? 'justify-center px-2' : 'px-6')}>
                <Link href="/" className="rounded-sm">
                    {collapsed ? (
                        <>
                            <span aria-hidden="true" className="font-display text-h3 font-bold text-text">
                                A<span className="text-primary">.</span>
                            </span>
                            <span className="sr-only">Amanah, home</span>
                        </>
                    ) : (
                        <>
                            <Wordmark />
                            <span className="sr-only">, home</span>
                        </>
                    )}
                </Link>
            </div>

            {/* Text on the patterned band uses text-secondary: text-muted drops below 4.5:1 where a pattern line runs under it. */}
            {portal && !collapsed && <p className="relative px-6 pb-2 text-overline text-text-secondary">{portal} portal</p>}

            <nav aria-label={`${portal} portal`} className={cx('relative flex-1 overflow-y-auto pb-4', collapsed ? 'px-3' : 'px-4')}>
                {groups.map((group, index) => (
                    <div key={group.label ?? index} className="mt-2 first:mt-0">
                        {group.label && !collapsed && <p className="px-3 pt-4 pb-2 text-overline text-text-secondary">{group.label}</p>}
                        <ul className="space-y-1">
                            {group.items.map((item) => (
                                <li key={item.href}>
                                    <NavItem item={item} collapsed={collapsed} onNavigate={onNavigate} />
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </nav>

            {(footer || collapsible) && (
                <div className={cx('relative space-y-3 border-t border-border py-4', collapsed ? 'px-3' : 'px-4')}>
                    {footer}
                    {collapsible && (
                        <button
                            type="button"
                            onClick={onToggleCollapse}
                            aria-expanded={!collapsed}
                            aria-label={collapsed ? 'Expand sidebar' : undefined}
                            className={cx(
                                'flex h-10 w-full items-center gap-3 rounded-md px-3 text-body-sm text-text-secondary hover:bg-band-hover',
                                collapsed && 'justify-center px-0',
                            )}
                        >
                            <Icon name={collapsed ? 'caret-double-right' : 'caret-double-left'} className="text-icon" />
                            {!collapsed && 'Collapse sidebar'}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

function NavItem({ item, collapsed, onNavigate }) {
    const link = (
        <Link
            href={item.href}
            onClick={() => onNavigate?.(item)}
            aria-current={item.active ? 'page' : undefined}
            className={cx(
                'relative flex h-10 items-center gap-3 rounded-md border text-body font-medium transition-colors duration-(--duration-fast) ease-standard pointer-coarse:min-h-(--touch-target)',
                collapsed ? 'justify-center px-0' : 'px-3',
                item.active
                    ? 'border-border bg-nav-active text-nav-active-ink shadow-xs'
                    : 'border-transparent text-text-secondary hover:bg-band-hover hover:text-text',
            )}
        >
            {item.active && <span aria-hidden="true" className="absolute inset-y-2 -left-px w-(--nav-indicator) rounded-r-sm bg-primary" />}
            <Icon name={item.icon} weight={item.active ? 'bold' : 'regular'} className={item.active ? 'text-primary' : 'text-icon'} />
            {/* Collapsed, the label stays in the link as screen-reader text, so its name still
                matches what is shown (the count) and it is read the same either way. */}
            <span className={collapsed ? 'sr-only' : 'truncate'}>{item.label}</span>
            {item.count !== undefined && (
                <span
                    className={cx(
                        'rounded-sm bg-primary-muted px-1.5 font-mono text-code text-primary-ink',
                        collapsed ? 'absolute -top-1 -right-1' : 'ml-auto',
                    )}
                >
                    {item.count}
                </span>
            )}
        </Link>
    );

    return collapsed ? (
        <Tooltip content={item.label} side="right">
            {link}
        </Tooltip>
    ) : (
        link
    );
}
