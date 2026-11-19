import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import cx from '../lib/cx';
import Alert from './Alert';
import MobileNav from './MobileNav';
import ModeSwitch, { useShariahSwitch } from './ModeSwitch';
import { navigationFor } from './navigation';
import PageTransition from './PageTransition';
import Sidebar from './Sidebar';
import { useToasts } from './Toast';
import TopBar from './TopBar';

const COLLAPSED_KEY = 'amanah.sidebar.collapsed';

/**
 * The app shell for every portal page, one per role through the role's navigation: sidebar,
 * top bar, and the page content (README, "Layout across devices").
 *
 * Set for portal pages in app.jsx, so Inertia keeps it mounted between pages: the sidebar and
 * top bar never re-render from scratch or animate, only the content inside PageTransition does.
 * A page gives it its title (and an optional `description` under the top bar) through
 * `Page.layout = { title: '...' }`, or a function of the page props for a dynamic title.
 *
 * The shell is a container (`@container/shell`): it responds to its own width, so it works in
 * a phone browser, a tablet, a desktop window or a split view.
 *   - 1024 px and up: 264 px sidebar (collapsible to 72), 32 px page padding, content up to 1200 px.
 *   - Below 1024 px: the sidebar becomes the MobileNav drawer behind the menu button.
 *   - Below 640 px: 16 px padding.
 */
export default function PortalLayout({ title, description, children }) {
    const page = usePage();
    const user = page.props.auth?.user;
    const navigation = navigationFor(user?.role, page.url);
    const shariahSwitch = useShariahSwitch();
    const [menuOpen, setMenuOpen] = useState(false);
    const [collapsed, setCollapsed] = useCollapsedSidebar();

    useFlashToasts();

    // Close the drawer whenever a visit starts (a nav link, a form redirect, the back button).
    useEffect(() => router.on('start', () => setMenuOpen(false)), []);

    return (
        <>
            <Head title={title} />
            <a
                href="#main"
                className="sr-only rounded-md bg-surface px-4 py-2 text-label text-text shadow-md focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-(--z-tooltip)"
            >
                Skip to content
            </a>

            <div className="@container/shell min-h-dvh bg-bg">
                <div className="flex min-h-dvh">
                    {/* The column is page-height with the band colour; the panel inside sticks to the viewport. */}
                    <aside
                        className={cx(
                            'hidden shrink-0 border-r border-border bg-band transition-[width] duration-(--duration-moderate) ease-standard motion-reduce:transition-none @bp-lg/shell:block',
                            collapsed ? 'w-(--sidebar-collapsed)' : 'w-(--sidebar)',
                        )}
                    >
                        <div className="sticky top-0 h-dvh">
                            <Sidebar
                                portal={navigation.portal}
                                groups={navigation.groups}
                                collapsed={collapsed}
                                onToggleCollapse={() => setCollapsed(!collapsed)}
                            />
                        </div>
                    </aside>

                    <div className="flex min-w-0 flex-1 flex-col">
                        <TopBar title={title} modeSwitch={shariahSwitch} user={user} onMenu={() => setMenuOpen(true)} />

                        <main id="main" tabIndex={-1} className="mx-auto w-full max-w-(--content-max) flex-1 px-4 py-6 outline-none @bp-sm/shell:px-6 @bp-lg/shell:px-8 @bp-lg/shell:py-8">
                            <PageTransition>
                                <div className="space-y-6">
                                    {description && <p className="max-w-prose text-body-lg text-text-secondary">{description}</p>}
                                    <AccountStatusNotice user={user} />
                                    {children}
                                </div>
                            </PageTransition>
                        </main>
                    </div>
                </div>
            </div>

            <MobileNav
                open={menuOpen}
                onClose={() => setMenuOpen(false)}
                portal={navigation.portal}
                groups={navigation.groups}
                footer={
                    shariahSwitch && (
                        <ModeSwitch
                            mode={shariahSwitch.mode}
                            onChange={shariahSwitch.change}
                            disabledReason={shariahSwitch.disabledReason}
                            compactLabel={false}
                        />
                    )
                }
            />
        </>
    );
}

/** The sidebar's collapsed state, remembered in this browser (a per-viewer convenience). */
function useCollapsedSidebar() {
    const [collapsed, setCollapsed] = useState(() => {
        try {
            return window.localStorage.getItem(COLLAPSED_KEY) === '1';
        } catch {
            return false;
        }
    });

    function update(next) {
        setCollapsed(next);
        try {
            window.localStorage.setItem(COLLAPSED_KEY, next ? '1' : '0');
        } catch {
            // Private mode or blocked storage: it just won't be remembered.
        }
    }

    return [collapsed, update];
}

/**
 * Shows the message the server flashed with ->with('status', '...') as a success toast, once per
 * response. The flash object is new on every full response and kept as-is on a partial reload,
 * so a background poll never shows an old message again.
 */
function useFlashToasts() {
    const { flash } = usePage().props;
    const { push } = useToasts();

    useEffect(() => {
        if (flash?.status) {
            push({ tone: 'success', title: flash.status });
        }
    }, [flash, push]);
}

/** A standing notice while the account is not yet verified. */
function AccountStatusNotice({ user }) {
    if (user?.status === 'pending_verification') {
        return (
            <Alert tone="warning" role="note" title="Your account is pending verification">
                An administrator will review your account once you have finished account setup and submitted it. Until it is
                verified you can sign in and update your details, but you can't raise financing or invest.
            </Alert>
        );
    }

    if (user?.status === 'rejected') {
        return (
            <Alert tone="danger" role="note" title="Your verification was not approved">
                <p>Reason: {user.status_reason}</p>
                <p>Fix what the administrator asked for in account setup, then submit your account again from its last step.</p>
            </Alert>
        );
    }

    if (user?.status === 'suspended') {
        return (
            <Alert tone="danger" role="note" title="Your account is suspended">
                {user.status_reason}
            </Alert>
        );
    }

    return null;
}
