import { useForm } from '@inertiajs/react';
import AuthLayout from '../../components/AuthLayout';
import Button from '../../components/Button';
import Input from '../../components/Input';
import { PASSWORD_HINT } from '../../components/PasswordHint';
import RadioGroup from '../../components/RadioGroup';
import TextLink from '../../components/TextLink';

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
        <AuthLayout
            title="Create an account"
            intro="Choose how you will use the platform."
            footer={
                <>
                    Already registered? <TextLink href="/login">Sign in</TextLink>
                </>
            }
        >
            {/* noValidate: the server's messages are the ones shown, not the browser's. */}
            <form onSubmit={submit} noValidate className="space-y-5">
                <RadioGroup
                    name="role"
                    legend="I want to register as"
                    options={roles.map((role) => ({
                        value: role.name,
                        label: role.label,
                        description: ROLE_DESCRIPTIONS[role.name],
                    }))}
                    value={form.data.role}
                    onChange={(value) => form.setData('role', value)}
                    error={form.errors.role}
                />

                <Input
                    id="name"
                    label="Full name"
                    autoComplete="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    error={form.errors.name}
                />

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
                    label="Password"
                    autoComplete="new-password"
                    hint={PASSWORD_HINT}
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />

                <Input
                    id="password_confirmation"
                    type="password"
                    label="Confirm password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    error={form.errors.password_confirmation}
                />

                <Button type="submit" processing={form.processing} fullWidth>
                    Create account
                </Button>
            </form>
        </AuthLayout>
    );
}
