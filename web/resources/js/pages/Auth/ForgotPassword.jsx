import { Link, useForm } from '@inertiajs/react';
import Field from '../../components/Field';
import SubmitButton from '../../components/SubmitButton';
import AuthLayout from '../../layouts/AuthLayout';

/**
 * "Forgot password" form (PB-08). The server answers the same way whether or not the email
 * has an account.
 */
export default function ForgotPassword() {
    const form = useForm({ email: '' });

    function submit(event) {
        event.preventDefault();
        form.post('/forgot-password');
    }

    return (
        <AuthLayout
            title="Forgot your password?"
            intro="Enter your email address and we'll send you a link to choose a new password. The link expires after 60 minutes."
        >
            <form onSubmit={submit} noValidate className="space-y-5">
                <Field
                    id="email"
                    type="email"
                    label="Email address"
                    autoComplete="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />

                <SubmitButton processing={form.processing}>Email me a reset link</SubmitButton>

                <p className="text-center text-sm text-slate-600">
                    <Link href="/login" className="font-medium text-emerald-700 hover:underline">
                        Back to sign in
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
