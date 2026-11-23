import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import Card from '../../components/Card';
import Input from '../../components/Input';
import MoneyInput from '../../components/MoneyInput';
import OnboardingFrame from '../../components/OnboardingFrame';
import RadioCards from '../../components/RadioCards';
import Select from '../../components/Select';
import TextLink from '../../components/TextLink';
import { toNumber } from '../../lib/format';

/**
 * The borrower's financial profile, an onboarding step (PB-58; on the financing request form
 * before). Declared once, checked by an administrator with the salary slip or financial
 * statement, and copied into each financing request when it is submitted.
 *
 * It stays open after verification. A verified borrower who changes it is told first that the
 * account goes back to the administrator, and can't submit new requests until it is approved.
 */
export default function OnboardingFinances({ wizard, finances, isSme, housing, employmentYears, verified, returnTo }) {
    const form = useForm({
        ...finances,
        monthly_income: toNumber(finances.monthly_income),
        monthly_debt_repayments: toNumber(finances.monthly_debt_repayments),
        return_to: returnTo ?? '',
    });

    function submit(event) {
        event.preventDefault();
        form.put('/borrower/onboarding/finances', { preserveScroll: true });
    }

    const money = (name) => ({
        id: name,
        value: form.data[name],
        onValueChange: (amount) => form.setData(name, amount),
        error: form.errors[name],
        disabled: wizard.locked,
    });
    // Whole numbers only (counts): anything but digits is dropped as it is typed.
    const count = (name) => ({
        id: name,
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value.replace(/\D/g, '')),
        error: form.errors[name],
        disabled: wizard.locked,
        inputMode: 'numeric',
        autoComplete: 'off',
        controlClassName: 'max-w-40',
    });

    return (
        <OnboardingFrame wizard={wizard} onSubmit={submit} processing={form.processing}>
            {returnTo && (
                <TextLink href={returnTo} icon="arrow-left">
                    Back to your financing request
                </TextLink>
            )}

            {verified && (
                <Alert tone="info" role="note" title="Changing these sends your account for checking again">
                    An administrator will check your new figures before you can submit new financing requests. Requests you have
                    already made keep the figures they were made with.
                </Alert>
            )}

            <Card title="Your finances" description="Used to assess your financing requests fairly. Investors don’t see these figures.">
                <div className="grid gap-5 @bp-sm/shell:grid-cols-2">
                    <MoneyInput
                        {...money('monthly_income')}
                        label="Monthly income before tax"
                        hint={isSme ? 'Your business’s average monthly revenue.' : 'As shown on your salary slip.'}
                    />
                    <MoneyInput
                        {...money('monthly_debt_repayments')}
                        label="Monthly repayments on existing loans and cards"
                        hint="Don’t include rent or a home loan. Enter 0 if you have none."
                    />
                    <Select
                        id="employment_years"
                        label={isSme ? 'Time running the business' : 'Time in your current job'}
                        placeholder="Choose how long"
                        options={employmentYears}
                        value={form.data.employment_years}
                        onChange={(value) => form.setData('employment_years', value)}
                        error={form.errors.employment_years}
                        disabled={wizard.locked}
                    />
                    <RadioCards
                        name="housing"
                        legend="Housing"
                        options={housing}
                        value={form.data.housing}
                        onChange={(value) => form.setData('housing', value)}
                        error={form.errors.housing}
                        disabled={wizard.locked}
                        className="@bp-sm/shell:col-span-2"
                    />
                </div>
            </Card>

            <Card
                title="Your credit history"
                description="Answer from your MMA Credit Information System report. An administrator checks your answers against the report you attach to a financing request."
            >
                <div className="space-y-5">
                    <Input {...count('open_credit_lines')} label="How many loans, financing facilities and credit cards do you have open?" hint="Count each one separately. Enter 0 if none." />
                    <Input {...count('late_payments_2y')} label="In the past 2 years, how many times was a repayment 30 or more days late?" hint="Across all your loans and cards. Enter 0 if never." />
                    <Input {...count('public_records')} label="How many court judgments, bankruptcies or debts written off have you had?" hint="Enter 0 if none." />
                </div>
            </Card>
        </OnboardingFrame>
    );
}

OnboardingFinances.layout = {
    title: 'Set up your account',
    description: 'A few steps, saved as you go. You can come back and finish later.',
};
