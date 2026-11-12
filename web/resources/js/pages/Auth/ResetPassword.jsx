import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import AuthLayout from '../../components/AuthLayout';
import Button from '../../components/Button';
import Input from '../../components/Input';
import { PASSWORD_HINT } from '../../components/PasswordHint';

/**
 * "Choose a new password" form (PB-08), opened from the emailed link. `token` and `email`
 * come from the link via ResetPasswordController.
 */
export default function ResetPassword({ token, email }) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();
        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthLayout title="Choose a new password">
            <form onSubmit={submit} noValidate className="space-y-5">
                {/* The token isn't a visible field, so its message is shown above the form. */}
                {form.errors.token && (
                    <Alert variant="error" role="alert">
                        {form.errors.token}
                    </Alert>
                )}

                <Input
                    id="email"
                    type="email"
                    label="Email address"
                    autoComplete="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />

                <Input
                    id="password"
                    type="password"
                    label="New password"
                    autoComplete="new-password"
                    hint={PASSWORD_HINT}
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />

                <Input
                    id="password_confirmation"
                    type="password"
                    label="Confirm new password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    error={form.errors.password_confirmation}
                />

                <Button type="submit" processing={form.processing} fullWidth>
                    Reset password
                </Button>
            </form>
        </AuthLayout>
    );
}
