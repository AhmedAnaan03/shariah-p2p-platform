import { Link, useForm } from '@inertiajs/react';
import Field from '../../components/Field';
import { PASSWORD_HINT } from '../../components/PasswordHint';
import SubmitButton from '../../components/SubmitButton';
import AuthLayout from '../../layouts/AuthLayout';

const ROLE_DESCRIPTIONS = {
    borrower: 'Raise financing for your business or a personal need.',
    investor: 'Fund campaigns and track your returns.',
};

/**
 * Registration form (PB-06). `roles` comes from RegisterController: borrower and investor only.
 */
export default function Register({ roles }) {
    // useForm keeps the field values, the server's errors and a "processing" flag.
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: '',
    });

    function submit(event) {
        event.preventDefault();
        // On a validation error Laravel redirects back and form.errors is filled in.
        form.post('/register', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthLayout title="Create an account" intro="Choose how you will use the platform.">
            {/* noValidate: the server's messages are the ones shown, not the browser's. */}
            <form onSubmit={submit} noValidate className="space-y-5">
                <fieldset>
                    <legend className="text-sm font-medium text-slate-700">I want to register as</legend>
                    <div className="mt-2 grid gap-3 sm:grid-cols-2">
                        {roles.map((role) => (
                            <label
                                key={role.name}
                                className={`flex cursor-pointer gap-3 rounded-md border p-3 text-sm ${
                                    form.data.role === role.name
                                        ? 'border-emerald-600 bg-emerald-50'
                                        : 'border-slate-300 hover:border-slate-400'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="role"
                                    value={role.name}
                                    checked={form.data.role === role.name}
                                    onChange={(e) => form.setData('role', e.target.value)}
                                    aria-describedby={form.errors.role ? 'role-error' : undefined}
                                    className="mt-0.5 accent-emerald-700"
                                />
                                <span>
                                    <span className="block font-semibold">{role.label}</span>
                                    <span className="block text-slate-600">{ROLE_DESCRIPTIONS[role.name]}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {form.errors.role && (
                        <p id="role-error" className="mt-1 text-sm text-red-600">
                            {form.errors.role}
                        </p>
                    )}
                </fieldset>

                <Field
                    id="name"
                    label="Full name"
                    autoComplete="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    error={form.errors.name}
                />

                <Field
                    id="email"
                    type="email"
                    label="Email address"
                    autoComplete="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />

                <Field
                    id="password"
                    type="password"
                    label="Password"
                    autoComplete="new-password"
                    hint={PASSWORD_HINT}
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />

                <Field
                    id="password_confirmation"
                    type="password"
                    label="Confirm password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    error={form.errors.password_confirmation}
                />

                <SubmitButton processing={form.processing}>Create account</SubmitButton>

                <p className="text-center text-sm text-slate-600">
                    Already registered?{' '}
                    <Link href="/login" className="font-medium text-emerald-700 hover:underline">
                        Sign in
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
