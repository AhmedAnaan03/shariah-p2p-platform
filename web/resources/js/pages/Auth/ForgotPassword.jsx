import { useForm } from '@inertiajs/react';
import AuthLayout from '../../components/AuthLayout';
import Button from '../../components/Button';
import Input from '../../components/Input';
import TextLink from '../../components/TextLink';

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
            footer={<TextLink href="/login">Back to sign in</TextLink>}
        >
            <form onSubmit={submit} noValidate className="space-y-5">
                <Input
                    id="email"
                    type="email"
                    label="Email address"
                    autoComplete="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />

                <Button type="submit" processing={form.processing} fullWidth>
                    Email me a reset link
                </Button>
            </form>
        </AuthLayout>
    );
}
