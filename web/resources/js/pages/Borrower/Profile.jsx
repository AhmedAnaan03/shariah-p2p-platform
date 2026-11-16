import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Card from '../../components/Card';
import Input from '../../components/Input';
import RadioCards from '../../components/RadioCards';
import Select from '../../components/Select';
import Textarea from '../../components/Textarea';

const TYPE_ICONS = { individual: 'user', sme: 'storefront' };

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
        <form onSubmit={submit} noValidate className="space-y-6">
            {locked && (
                <Alert tone="info" role="note">
                    Your account has been verified, so your profile can no longer be changed.
                </Alert>
            )}

            <Card title="About you">
                <div className="space-y-5">
                    <RadioCards
                        name="borrower_type"
                        legend="I am borrowing as"
                        options={borrowerTypes.map((type) => ({ ...type, icon: TYPE_ICONS[type.value] }))}
                        value={form.data.borrower_type}
                        onChange={(value) => form.setData('borrower_type', value)}
                        disabled={locked}
                        error={form.errors.borrower_type}
                    />

                    <fieldset className="grid gap-5 @bp-sm/shell:grid-cols-2">
                        <legend className="sr-only">Personal details</legend>
                        <Input {...field('phone')} type="tel" label="Phone number" autoComplete="tel" hint="For example 7771234 or +960 7771234." />
                        <Input {...field('national_id_no')} label="National ID card number" hint="The letter A and 6 digits, like A123456." />
                        <Textarea {...field('address')} label="Address" className="@bp-sm/shell:col-span-2" />
                    </fieldset>
                </div>
            </Card>

            {isSme && (
                <Card title="Your business">
                    <fieldset className="grid gap-5 @bp-sm/shell:grid-cols-2">
                        <legend className="sr-only">Business details</legend>
                        <Input {...field('business_name')} label="Business name" autoComplete="organization" />
                        <Input {...field('business_registration_no')} label="Business registration number" hint="As on your registration certificate, like C-0412/2021." />
                        <Select
                            {...field('sector')}
                            onChange={(value) => form.setData('sector', value)}
                            label="Sector"
                            placeholder="Choose a sector"
                            options={sectors}
                        />
                        <Input {...field('years_operating')} type="number" min="0" max="100" label="Years operating" />
                    </fieldset>
                </Card>
            )}

            <Card title={isSme ? 'Revenue' : 'Income'}>
                <Input
                    {...field('declared_annual_income')}
                    inputMode="decimal"
                    prefix="MVR"
                    label={isSme ? 'Declared annual revenue' : 'Declared annual income'}
                    hint="Your own figure before tax. It is used in the risk assessment."
                    className="max-w-sm"
                />
            </Card>

            {!locked && (
                <Button type="submit" loading={form.processing}>
                    Save profile
                </Button>
            )}
        </form>
    );
}

BorrowerProfile.layout = {
    title: 'Your profile',
    description: 'An administrator checks these details against your documents before you can raise financing.',
};
