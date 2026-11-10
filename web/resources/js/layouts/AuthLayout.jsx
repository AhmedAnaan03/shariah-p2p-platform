import { Head, Link } from '@inertiajs/react';
import StatusMessage from '../components/StatusMessage';

/**
 * Centred card used by the register, sign-in and password pages.
 */
export default function AuthLayout({ title, intro, children }) {
    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-slate-50 px-4 py-12 text-slate-900 sm:px-6">
                <div className="mx-auto w-full max-w-md">
                    <Link href="/" className="text-sm font-medium text-emerald-700 hover:underline">
                        Shariah-Compliant P2P Financing Platform
                    </Link>

                    <div className="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                        <h1 className="text-2xl font-semibold">{title}</h1>
                        {intro && <p className="mt-2 text-sm text-slate-600">{intro}</p>}

                        <div className="mt-4">
                            <StatusMessage />
                        </div>

                        <div className="mt-6">{children}</div>
                    </div>
                </div>
            </div>
        </>
    );
}
