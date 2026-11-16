import { Head, usePage } from '@inertiajs/react';
import Alert from '../components/Alert';
import Button from '../components/Button';
import Card from '../components/Card';
import PageBand from '../components/PageBand';
import PageHeading from '../components/PageHeading';
import SiteHeader from '../components/SiteHeader';

export default function Welcome({ roles }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="@container/shell min-h-dvh">
                <SiteHeader
                    actions={
                        auth.user ? (
                            <Button href={`/${auth.user.role}`} size="sm" iconRight="arrow-right">
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
                        )
                    }
                />

                <main className="mx-auto max-w-(--content-max) space-y-8 px-4 py-8 @bp-sm/shell:px-8 @bp-sm/shell:py-12">
                    <PageBand>
                        <PageHeading
                            eyebrow="Maldives · P2P financing & SME crowdinvesting"
                            title="Shariah-Compliant P2P Financing Platform"
                            lead="Connects borrowers and SMEs with investors, with AI-assisted risk analysis and an optional Shariah-compliant mode."
                        />
                    </PageBand>

                    <div className="grid gap-6 @bp-md/shell:grid-cols-3">
                        {roles.map((role) => (
                            <Card key={role.name} title={role.name} description={role.description} />
                        ))}
                    </div>

                    <Alert tone="info" role="note">
                        Academic prototype: all money movements are simulated. Shariah mode is rule-based and is not a formal
                        Shariah certification.
                    </Alert>
                </main>
            </div>
        </>
    );
}
