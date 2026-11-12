import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Card from '../../components/Card';
import Input from '../../components/Input';
import PortalLayout from '../../components/PortalLayout';
import RadioGroup from '../../components/RadioGroup';
import Select from '../../components/Select';
import Textarea from '../../components/Textarea';

/**
 * Borrower / SME profile form (PB-09). `profile` is null until it is first saved. The
 * business section only appears for an SME; for an individual those fields aren't sent.
 */
export default function BorrowerProfile({ profile, borrowerTypes, sectors, locked }) {
    const form = useForm({
        borrower_type: profile?.borrower_type ?? '',
        phone: profile?.phone ?? '',
        national_id_no: profile?.national_id_no ?? '',
        address: profile?.address ?? '',
        business_name: profile?.business_name ?? '',
        business_registration_no: profile?.business_registration_no ?? '',
        sector: profile?.sector ?? '',
        years_operating: profile?.years_operating ?? '',
        declared_annual_income: profile?.declared_annual_income ?? '',
    });

    const isSme = form.data.borrower_type === 'sme';

    function submit(event) {
        event.preventDefault();
        form.put('/borrower/profile', { preserveScroll: true });
    }

    // Each field's common props: value, change handler, server error, and locked once verified.
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
                description="An administrator checks these details against your documents before you can raise financing."
            >
                {locked && (
                    <Alert variant="info" role="note" className="mb-5">
                        Your account has been verified, so your profile can no longer be changed.
                    </Alert>
                )}

                <form onSubmit={submit} noValidate className="space-y-6">
                    <RadioGroup
                        name="borrower_type"
                        legend="I am borrowing as"
                        options={borrowerTypes}
                        value={form.data.borrower_type}
                        onChange={(value) => !locked && form.setData('borrower_type', value)}
                        error={form.errors.borrower_type}
                    />

                    <fieldset className="grid gap-5 sm:grid-cols-2">
                        <legend className="sr-only">Personal details</legend>
                        <Input {...field('phone')} type="tel" label="Phone number" autoComplete="tel" hint="For example 7771234 or +960 7771234." />
                        <Input {...field('national_id_no')} label="National ID card number" hint="The letter A and 6 digits, like A123456." />
                        <Textarea {...field('address')} label="Address" className="sm:col-span-2" />
                    </fieldset>

                    {isSme && (
                        <fieldset className="grid gap-5 sm:grid-cols-2">
                            <legend className="sr-only">Business details</legend>
                            <Input {...field('business_name')} label="Business name" autoComplete="organization" />
                            <Input {...field('business_registration_no')} label="Business registration number" />
                            <Select {...field('sector')} label="Sector" placeholder="Choose a sector" options={sectors} />
                            <Input {...field('years_operating')} type="number" min="0" max="100" label="Years operating" />
                        </fieldset>
                    )}

                    <Input
                        {...field('declared_annual_income')}
                        inputMode="decimal"
                        label={isSme ? 'Declared annual revenue (MVR)' : 'Declared annual income (MVR)'}
                        hint="Your own figure before tax. It is used in the risk assessment."
                        className="sm:max-w-xs"
                    />

                    {!locked && (
                        <Button type="submit" processing={form.processing}>
                            Save profile
                        </Button>
                    )}
                </form>
            </Card>
        </PortalLayout>
    );
}
