import { Head } from '@inertiajs/react';
import Card from './Card';
import StatusMessage from './StatusMessage';
import TextLink from './TextLink';

/**
 * Centred card used by the register, sign-in and password pages. `footer` is the small line
 * under the form, e.g. "New here? Create an account".
 */
export default function AuthLayout({ title, intro, footer, children }) {
    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-slate-50 px-4 py-12 text-slate-900 sm:px-6">
                <div className="mx-auto w-full max-w-md">
                    <TextLink href="/" className="text-sm">
                        Shariah-Compliant P2P Financing Platform
                    </TextLink>

                    <Card title={title} titleAs="h1" description={intro} className="mt-6 sm:p-8">
                        <div className="space-y-6">
                            <StatusMessage />
                            {children}
                            {footer && <p className="text-center text-sm text-slate-600">{footer}</p>}
                        </div>
                    </Card>
                </div>
            </div>
        </>
    );
}
