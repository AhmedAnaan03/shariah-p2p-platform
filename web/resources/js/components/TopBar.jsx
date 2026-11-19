import { router } from '@inertiajs/react';
import Avatar from './Avatar';
import Button from './Button';
import DropdownMenu from './DropdownMenu';
import EmptyState from './EmptyState';
import Icon from './Icon';
import ModeSwitch from './ModeSwitch';
import Popover from './Popover';

/**
 * The white bar above every portal page (docs/design-system/components/TopBar.md): the menu
 * button (below 1024 px), the page title, the Shariah-only switch for investors, the
 * notifications bell and the user menu. 64 px tall, sticky, never animates.
 *
 * The title is the page's h1. Global search is off until the platform has something to
 * search (`search` is kept for later); a search box that did nothing would mislead.
 */
export default function TopBar({ title, breadcrumb, modeSwitch, unread = 0, user, onMenu }) {
    return (
        <header className="sticky top-0 z-(--z-sticky) flex h-(--topbar) shrink-0 items-center gap-3 border-b border-border bg-surface px-4 @bp-sm/shell:px-6 @bp-lg/shell:px-8">
            {onMenu && (
                <Button icon="list" variant="ghost" aria-label="Open menu" onClick={onMenu} className="-ml-2 @bp-lg/shell:hidden" />
            )}

            <div className="min-w-0 flex-1">
                {breadcrumb && <div className="min-w-0 truncate">{breadcrumb}</div>}
                <h1 className="truncate text-h4 text-text @bp-sm/shell:text-h3">{title}</h1>
            </div>

            <div className="flex shrink-0 items-center gap-1 @bp-sm/shell:gap-2">
                {modeSwitch && <ModeSwitch mode={modeSwitch.mode} onChange={modeSwitch.change} disabledReason={modeSwitch.disabledReason} />}
                <NotificationsBell unread={unread} />
                {user && <UserMenu user={user} />}
            </div>
        </header>
    );
}

function NotificationsBell({ unread }) {
    const label = unread === 0 ? 'Notifications, none unread' : `Notifications, ${unread} unread`;

    return (
        <Popover
            label="Notifications"
            trigger={
                <button
                    type="button"
                    aria-label={label}
                    className="relative flex size-(--control-md) items-center justify-center rounded-md text-text-secondary hover:bg-surface-subtle hover:text-text pointer-coarse:size-(--touch-target)"
                >
                    <Icon name="bell" />
                    {unread > 0 && (
                        <span
                            aria-hidden="true"
                            className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 font-mono text-overline text-primary-fg ring-2 ring-surface"
                        >
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </button>
            }
        >
            <p className="border-b border-border px-4 py-3 text-overline text-text-muted">Notifications</p>
            {/* Notifications arrive with PB-39. */}
            <EmptyState kind="notifications" title="No notifications yet" compact>
                We'll tell you here when something needs your attention.
            </EmptyState>
        </Popover>
    );
}

function UserMenu({ user }) {
    return (
        <DropdownMenu
            trigger={
                // The accessible name is the visible name and role plus "account menu", so what a
                // voice-control user sees is what they can say (WCAG 2.5.3).
                <button
                    type="button"
                    className="flex h-(--control-md) items-center gap-2 rounded-md px-1.5 text-left hover:bg-surface-subtle pointer-coarse:min-h-(--touch-target) @bp-sm/shell:px-2"
                >
                    <Avatar name={user.name} />
                    <span className="sr-only min-w-0 @bp-md/shell:not-sr-only">
                        <span className="block max-w-40 truncate text-label text-text">{user.name}</span>
                        <span className="block text-body-sm text-text-muted">{user.role_label}</span>
                    </span>
                    <span className="sr-only">, account menu</span>
                    <Icon name="caret-down" size={16} className="hidden text-icon @bp-md/shell:block" />
                </button>
            }
            items={[
                { heading: `Signed in as ${user.email}` },
                { icon: 'key', label: 'Change password', onSelect: () => router.visit('/account/password') },
                '-',
                // Sign-out is a POST (with the CSRF token), never a plain link.
                { icon: 'sign-out', label: 'Sign out', onSelect: () => router.post('/logout') },
            ]}
        />
    );
}
