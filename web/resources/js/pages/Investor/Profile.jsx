import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Card from '../../components/Card';
import Input from '../../components/Input';
import PortalLayout from '../../components/PortalLayout';
import Textarea from '../../components/Textarea';

/**
 * Investor profile form (PB-10). `profile` is null until it is first saved.
 */
export default function InvestorProfile({ profile, locked }) {
    const form = useForm({
        phone: profile?.phone ?? '',
        national_id_no: profile?.national_id_no ?? '',
        address: profile?.address ?? '',
    });

    function submit(event) {
        event.preventDefault();
        form.put('/investor/profile', { preserveScroll: true });
    }

    const field = (name) => ({
        id: name,
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value),
        error: form.errors[name],
        disabled: locked,
    });

    return (
        <PortalLayout title="Your profile">
            <Card
                title="Your profile"
                titleAs="h1"
                description="An administrator checks these details against your ID document before you can invest."
                className="max-w-2xl"
            >
                {locked && (
                    <Alert variant="info" role="note" className="mb-5">
                        Your account has been verified, so your profile can no longer be changed.
                    </Alert>
                )}

                <form onSubmit={submit} noValidate className="grid gap-5 sm:grid-cols-2">
                    <Input {...field('phone')} type="tel" label="Phone number" autoComplete="tel" hint="For example 7771234 or +960 7771234." />
                    <Input {...field('national_id_no')} label="National ID card number" hint="The letter A and 6 digits, like A123456." />
                    <Textarea {...field('address')} label="Address" className="sm:col-span-2" />

                    {!locked && (
                        <div className="sm:col-span-2">
                            <Button type="submit" processing={form.processing}>
                                Save profile
                            </Button>
                        </div>
                    )}
                </form>
            </Card>
        </PortalLayout>
    );
}
