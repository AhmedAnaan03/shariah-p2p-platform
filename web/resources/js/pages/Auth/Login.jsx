import { Link, useForm } from '@inertiajs/react';
import Field from '../../components/Field';
import SubmitButton from '../../components/SubmitButton';
import AuthLayout from '../../layouts/AuthLayout';

/**
 * Sign-in form (PB-07). Wrong details and the rate-limit lock both come back as an error on
 * the email field.
 */
export default function Login() {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event) {
        event.preventDefault();
        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <AuthLayout title="Sign in">
            <form onSubmit={submit} noValidate className="space-y-5">
                <Field
                    id="email"
                    type="email"
                    label="Email address"
                    autoComplete="username"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />

                <Field
                    id="password"
                    type="password"
                    label="Password"
                    autoComplete="current-password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />

                <div className="flex items-center justify-between text-sm">
                    <label className="flex items-center gap-2 text-slate-700">
                        <input
                            type="checkbox"
                            checked={form.data.remember}
                            onChange={(e) => form.setData('remember', e.target.checked)}
                            className="accent-emerald-700"
                        />
                        Remember me
                    </label>
                    <Link href="/forgot-password" className="font-medium text-emerald-700 hover:underline">
                        Forgot password?
                    </Link>
                </div>

                <SubmitButton processing={form.processing}>Sign in</SubmitButton>

                <p className="text-center text-sm text-slate-600">
                    New here?{' '}
                    <Link href="/register" className="font-medium text-emerald-700 hover:underline">
                        Create an account
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
