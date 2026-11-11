import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome({ roles }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="min-h-screen bg-slate-50 text-slate-900">
                <nav className="mx-auto flex max-w-4xl justify-end gap-3 px-4 pt-6 text-sm sm:px-6">
                    {auth.user ? (
                        <Link
                            href={`/${auth.user.role}`}
                            className="rounded-md bg-emerald-700 px-3 py-1.5 font-semibold text-white hover:bg-emerald-800"
                        >
                            Go to your portal
                        </Link>
                    ) : (
                        <>
                            <Link href="/login" className="rounded-md px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100">
                                Sign in
                            </Link>
                            <Link
                                href="/register"
                                className="rounded-md bg-emerald-700 px-3 py-1.5 font-semibold text-white hover:bg-emerald-800"
                            >
                                Create an account
                            </Link>
                        </>
                    )}
                </nav>

                <main className="mx-auto max-w-4xl px-4 py-12 sm:px-6">
                    <p className="text-sm font-medium uppercase tracking-wide text-emerald-700">
                        Maldives · P2P financing &amp; SME crowdinvesting
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">
                        Shariah-Compliant P2P Financing Platform
                    </h1>
                    <p className="mt-4 max-w-2xl text-slate-600">
                        Connects borrowers and SMEs with investors, with AI-assisted risk
                        analysis and an optional Shariah-compliant mode.
                    </p>

                    <section className="mt-10 grid gap-4 sm:grid-cols-3">
                        {roles.map((role) => (
                            <div
                                key={role.name}
                                className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <h2 className="font-semibold">{role.name}</h2>
                                <p className="mt-2 text-sm text-slate-600">{role.description}</p>
                            </div>
                        ))}
                    </section>

                    <p className="mt-12 text-xs text-slate-500">
                        Academic prototype: all money movements are simulated. Shariah mode is
                        rule-based and is not a formal Shariah certification.
                    </p>
                </main>
            </div>
        </>
    );
}
