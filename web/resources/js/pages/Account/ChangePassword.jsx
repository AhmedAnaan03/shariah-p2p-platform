import { useForm } from '@inertiajs/react';
import Button from '../../components/Button';
import Card from '../../components/Card';
import Input from '../../components/Input';
import { PASSWORD_HINT } from '../../components/PasswordHint';

/**
 * Change password for a signed-in user of any role (PB-08).
 */
export default function ChangePassword() {
    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();
        form.put('/account/password', {
            preserveScroll: true,
            // Clear all three boxes on success and on failure; passwords are never kept.
            onFinish: () => form.reset(),
        });
    }

    return (
        <Card title="Your password" description="Enter your current password, then choose a new one." className="max-w-lg">
            <form onSubmit={submit} noValidate className="space-y-5">
                <Input
                    id="current_password"
                    type="password"
                    label="Current password"
                    autoComplete="current-password"
                    value={form.data.current_password}
                    onChange={(e) => form.setData('current_password', e.target.value)}
                    error={form.errors.current_password}
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

                <Button type="submit" loading={form.processing}>
                    Change password
                </Button>
            </form>
        </Card>
    );
}

// The layout (PortalLayout, set in app.jsx) receives these as props.
ChangePassword.layout = { title: 'Change password' };
