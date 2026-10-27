import { Head } from '@inertiajs/react';

export default function Welcome({ roles }) {
    return (
        <>
            <Head title="Welcome" />

            <div className="min-h-screen bg-slate-50 text-slate-900">
                <main className="mx-auto max-w-4xl px-4 py-16 sm:px-6">
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
                                <span className="mt-4 inline-block rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">
                                    Portal coming soon
                                </span>
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
