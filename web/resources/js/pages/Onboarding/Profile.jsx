import { useForm } from '@inertiajs/react';
import Card from '../../components/Card';
import Input from '../../components/Input';
import OnboardingFrame from '../../components/OnboardingFrame';
import RadioCards from '../../components/RadioCards';
import Select from '../../components/Select';

const TYPE_ICONS = { individual: 'user', sme: 'storefront' };

/**
 * Onboarding step 1, "Your details" (PB-53; the fields are PB-09's borrower profile and PB-10's
 * investor profile; income moved to the finance step in PB-58). `profile` is null until it is first saved. A borrower also chooses
 * individual or SME, which decides their business fields here and their document slots later.
 */
export default function OnboardingProfile({ wizard, profile, borrowerTypes, sectors }) {
    const borrower = wizard.role === 'borrower';
    const form = useForm(
        borrower
            ? {
                  borrower_type: profile?.borrower_type ?? '',
                  phone: profile?.phone ?? '',
                  national_id_no: profile?.national_id_no ?? '',
                  business_name: profile?.business_name ?? '',
                  business_registration_no: profile?.business_registration_no ?? '',
                  sector: profile?.sector ?? '',
                  years_operating: profile?.years_operating ?? '',
              }
            : { phone: profile?.phone ?? '', national_id_no: profile?.national_id_no ?? '' },
    );

    const isSme = form.data.borrower_type === 'sme';

    function submit(event) {
        event.preventDefault();
        form.put(`/${wizard.role}/onboarding/profile`, { preserveScroll: true });
    }

    // Each field's common props: value, change handler, server error, and locked once verified.
    const field = (name) => ({
        id: name,
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value),
        error: form.errors[name],
        disabled: wizard.locked,
    });

    return (
        <OnboardingFrame wizard={wizard} onSubmit={submit} processing={form.processing}>
            <Card title="About you" description="An administrator checks these details against your documents.">
                <div className="space-y-5">
                    {borrower && (
                        <RadioCards
                            name="borrower_type"
                            legend="I am borrowing as"
                            options={borrowerTypes.map((type) => ({ ...type, icon: TYPE_ICONS[type.value] }))}
                            value={form.data.borrower_type}
                            onChange={(value) => form.setData('borrower_type', value)}
                            disabled={wizard.locked}
                            error={form.errors.borrower_type}
                        />
                    )}

                    <fieldset className="grid gap-5 @bp-sm/shell:grid-cols-2">
                        <legend className="sr-only">Personal details</legend>
                        <Input {...field('phone')} type="tel" label="Phone number" autoComplete="tel" hint="For example 7771234 or +960 7771234." />
                        <Input {...field('national_id_no')} label="National ID card number" hint="The letter A and 6 digits, like A123456." />
                    </fieldset>
                </div>
            </Card>

            {borrower && isSme && (
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

        </OnboardingFrame>
    );
}

OnboardingProfile.layout = {
    title: 'Set up your account',
    description: 'A few steps, saved as you go. You can come back and finish later.',
};
