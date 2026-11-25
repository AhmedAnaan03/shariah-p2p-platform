import { Head, Link } from '@inertiajs/react';
import BackgroundPattern from './BackgroundPattern';
import Card from './Card';
import PageTransition from './PageTransition';
import StatusMessage from './StatusMessage';
import Wordmark from './Wordmark';
import { PLATFORM_NAME } from '../lib/brand';

/**
 * The frame for the register, sign-in and password pages: the wordmark on a patterned band, a
 * centred card with the form, and the prototype note. Set for Auth/* pages in app.jsx, so it
 * stays mounted while the user moves between them. A page gives it `title`, `intro` and
 * `footer` (the small line under the form, e.g. "New here? Create an account") through
 * `Page.layout = { ... }`.
 *
 * Flash messages show inline here (not as a toast): "We've emailed you a reset link" is
 * something to read before going on.
 */
export default function AuthLayout({ title, intro, footer, children }) {
    return (
        <>
            <Head title={title} />

            <div className="@container/shell relative min-h-dvh bg-bg">
                <div aria-hidden="true" className="absolute inset-x-0 top-0 h-64 border-b border-border bg-band">
                    <BackgroundPattern fade="down" />
                </div>

                <div className="relative mx-auto w-full max-w-md px-4 py-10 @bp-sm/shell:py-16">
                    <Link href="/" aria-label={`${PLATFORM_NAME}, home`} className="inline-block rounded-sm">
                        <Wordmark size="lg" />
                    </Link>

                    <PageTransition>
                        <Card title={title} titleAs="h1" description={intro} className="mt-6 shadow-sm @bp-sm/shell:p-8">
                            <div className="space-y-6">
                                <StatusMessage />
                                {children}
                                {footer && <p className="text-center text-body text-text-muted">{footer}</p>}
                            </div>
                        </Card>
                    </PageTransition>

                    <p className="mt-6 text-center text-body-sm text-text-muted">Academic prototype. All money on this platform is simulated.</p>
                </div>
            </div>
        </>
    );
}
