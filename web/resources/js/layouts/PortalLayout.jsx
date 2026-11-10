import { Head, Link, usePage } from '@inertiajs/react';
import StatusMessage from '../components/StatusMessage';

/**
 * Frame for every portal page: header with the user's name and role, links to change
 * password and sign out, and the pending-verification notice while the account is pending.
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
                        <nav className="flex items-center gap-4 text-sm">
                            <Link href="/account/password" className="text-slate-600 hover:text-slate-900 hover:underline">
                                Change password
                            </Link>
                            {/* Sign-out is a POST (with the CSRF token), never a plain link. */}
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-100"
                            >
                                Sign out
                            </Link>
                        </nav>
                    </div>
                </header>

                <main className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
                    <StatusMessage />

                    {user.status === 'pending_verification' && (
                        <div
                            role="note"
                            className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                        >
                            <p className="font-semibold">Your account is pending verification</p>
                            <p className="mt-1">
                                An administrator will review your account. Until it is verified, you can
                                sign in and complete your profile, but you can't raise financing or invest.
                            </p>
                        </div>
                    )}

                    {children}
                </main>
            </div>
        </>
    );
}
