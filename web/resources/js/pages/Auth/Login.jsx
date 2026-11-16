import { useForm } from '@inertiajs/react';
import Button from '../../components/Button';
import Checkbox from '../../components/Checkbox';
import Input from '../../components/Input';
import TextLink from '../../components/TextLink';

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
        <form onSubmit={submit} noValidate className="space-y-5">
            <Input
                id="email"
                type="email"
                label="Email address"
                autoComplete="username"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
                error={form.errors.email}
            />

            <Input
                id="password"
                type="password"
                label="Password"
                autoComplete="current-password"
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
                error={form.errors.password}
            />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Checkbox
                    id="remember"
                    label="Remember me"
                    checked={form.data.remember}
                    onChange={(checked) => form.setData('remember', checked)}
                />
                <TextLink href="/forgot-password">Forgot password?</TextLink>
            </div>

            <Button type="submit" loading={form.processing} fullWidth>
                Sign in
            </Button>
        </form>
    );
}

// The layout (AuthLayout, set in app.jsx) receives these as props.
Login.layout = {
    title: 'Sign in',
    footer: (
        <>
            New here? <TextLink href="/register">Create an account</TextLink>
        </>
    ),
};
