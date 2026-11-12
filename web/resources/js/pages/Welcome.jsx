import { Head, usePage } from '@inertiajs/react';
import Alert from '../components/Alert';
import Button from '../components/Button';
import Card from '../components/Card';
import PageHeading from '../components/PageHeading';

export default function Welcome({ roles }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="min-h-screen bg-slate-50 text-slate-900">
                <nav className="mx-auto flex max-w-4xl justify-end gap-3 px-4 pt-6 sm:px-6">
                    {auth.user ? (
                        <Button href={`/${auth.user.role}`} size="sm">
                            Go to your portal
                        </Button>
                    ) : (
                        <>
                            <Button href="/login" variant="secondary" size="sm">
                                Sign in
                            </Button>
                            <Button href="/register" size="sm">
                                Create an account
                            </Button>
                        </>
                    )}
                </nav>

                <main className="mx-auto max-w-4xl space-y-10 px-4 py-12 sm:px-6">
                    <PageHeading
                        eyebrow="Maldives · P2P financing & SME crowdinvesting"
                        title="Shariah-Compliant P2P Financing Platform"
                        lead="Connects borrowers and SMEs with investors, with AI-assisted risk analysis and an optional Shariah-compliant mode."
                    />

                    <div className="grid gap-4 sm:grid-cols-3">
                        {roles.map((role) => (
                            <Card key={role.name} title={role.name} description={role.description} />
                        ))}
                    </div>

                    <Alert variant="info" role="note">
                        Academic prototype: all money movements are simulated. Shariah mode is rule-based and is not a
                        formal Shariah certification.
                    </Alert>
                </main>
            </div>
        </>
    );
}
