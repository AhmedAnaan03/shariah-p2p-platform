import { Link, useForm, usePage } from '@inertiajs/react';
import Field from '../../components/Field';
import { PASSWORD_HINT } from '../../components/PasswordHint';
import SubmitButton from '../../components/SubmitButton';
import PortalLayout from '../../layouts/PortalLayout';

/**
 * Change password for a signed-in user of any role (PB-08).
 */
export default function ChangePassword() {
    const { auth } = usePage().props;

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
        <PortalLayout title="Change password">
            <section className="max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h1 className="text-xl font-semibold">Change password</h1>

                <form onSubmit={submit} noValidate className="mt-6 space-y-5">
                    <Field
                        id="current_password"
                        type="password"
                        label="Current password"
                        autoComplete="current-password"
                        value={form.data.current_password}
                        onChange={(e) => form.setData('current_password', e.target.value)}
                        error={form.errors.current_password}
                    />

                    <Field
                        id="password"
                        type="password"
                        label="New password"
                        autoComplete="new-password"
                        hint={PASSWORD_HINT}
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        error={form.errors.password}
                    />

                    <Field
                        id="password_confirmation"
                        type="password"
                        label="Confirm new password"
                        autoComplete="new-password"
                        value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                        error={form.errors.password_confirmation}
                    />

                    <SubmitButton processing={form.processing}>Change password</SubmitButton>
                </form>

                <p className="mt-4 text-sm">
                    <Link href={`/${auth.user.role}`} className="font-medium text-emerald-700 hover:underline">
                        Back to your portal
                    </Link>
                </p>
            </section>
        </PortalLayout>
    );
}
