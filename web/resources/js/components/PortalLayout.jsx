import { Head, Link, usePage } from '@inertiajs/react';
import Alert from './Alert';
import Button from './Button';
import StatusMessage from './StatusMessage';

// The pages each role's navigation links to. Every link stays inside that role's portal, so
// the role gate on the routes and the links here always agree.
const NAVIGATION = {
    borrower: [
        { href: '/borrower', label: 'Overview' },
        { href: '/borrower/profile', label: 'Profile' },
        { href: '/borrower/documents', label: 'Documents' },
    ],
    investor: [
        { href: '/investor', label: 'Overview' },
        { href: '/investor/profile', label: 'Profile' },
        { href: '/investor/documents', label: 'Documents' },
        { href: '/investor/acknowledgement', label: 'Risk acknowledgement' },
    ],
    admin: [
        { href: '/admin', label: 'Overview' },
        { href: '/admin/verifications', label: 'Verifications' },
    ],
};

/**
 * Frame for every portal page: header with the user's name, role and navigation, links to
 * change password and sign out, the flash message, and a notice while the account is not yet
 * verified (pending, rejected with the admin's reason, or suspended).
 */
export default function PortalLayout({ title, children }) {
    const { auth } = usePage().props;
    const user = auth.user;

    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-slate-50 text-slate-900">
                <header className="border-b border-slate-200 bg-white">
                    <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                        <div>
                            <p className="text-sm font-semibold text-emerald-700">{user.role_label} portal</p>
                            <p className="text-xs text-slate-500">Signed in as {user.name}</p>
                        </div>
                        <div className="flex items-center gap-4 text-sm">
                            <Link href="/account/password" className="text-slate-600 hover:text-slate-900 hover:underline">
                                Change password
                            </Link>
                            {/* Sign-out is a POST (with the CSRF token), never a plain link. */}
                            <Button href="/logout" method="post" variant="secondary" size="sm">
                                Sign out
                            </Button>
                        </div>
                    </div>
                    <PortalNavigation links={NAVIGATION[user.role] ?? []} />
                </header>

                <main className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
                    <StatusMessage />
                    <AccountStatusNotice user={user} />
                    {children}
                </main>
            </div>
        </>
    );
}

function PortalNavigation({ links }) {
    const { url } = usePage();
    // The longest matching link is the current one, so /borrower/profile highlights "Profile",
    // not "Overview".
    const current = links
        .filter((link) => url === link.href || url.startsWith(`${link.href}/`) || url.startsWith(`${link.href}?`))
        .sort((a, b) => b.href.length - a.href.length)[0];

    return (
        <nav aria-label="Portal" className="mx-auto flex max-w-5xl gap-1 overflow-x-auto px-4 sm:px-6">
            {links.map((link) => {
                const active = link === current;

                return (
                    <Link
                        key={link.href}
                        href={link.href}
                        aria-current={active ? 'page' : undefined}
                        className={`whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium ${
                            active ? 'border-emerald-700 text-emerald-800' : 'border-transparent text-slate-600 hover:text-slate-900'
                        }`}
                    >
                        {link.label}
                    </Link>
                );
            })}
        </nav>
    );
}

function AccountStatusNotice({ user }) {
    if (user.status === 'pending_verification') {
        return (
            <Alert variant="warning" role="note" title="Your account is pending verification">
                An administrator will review your account once your profile and documents are complete. Until it is
                verified you can sign in and update your details, but you can't raise financing or invest.
            </Alert>
        );
    }

    if (user.status === 'rejected') {
        return (
            <Alert variant="error" role="note" title="Your verification was not approved">
                <p>Reason: {user.status_reason}</p>
                <p className="mt-1">
                    Update your profile or upload a new document and your account will go back into the review queue.
                </p>
            </Alert>
        );
    }

    if (user.status === 'suspended') {
        return (
            <Alert variant="error" role="note" title="Your account is suspended">
                {user.status_reason}
            </Alert>
        );
    }

    return null;
}
