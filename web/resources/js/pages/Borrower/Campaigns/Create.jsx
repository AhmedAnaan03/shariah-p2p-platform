import { useForm } from '@inertiajs/react';
import Alert from '../../../components/Alert';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import ContractTypePicker from '../../../components/ContractTypePicker';
import DescriptionList from '../../../components/DescriptionList';
import FileDropzone from '../../../components/FileDropzone';
import Input from '../../../components/Input';
import Money from '../../../components/Money';
import MoneyInput from '../../../components/MoneyInput';
import PercentInput from '../../../components/PercentInput';
import RadioCards from '../../../components/RadioCards';
import Select from '../../../components/Select';
import Term from '../../../components/Term';
import Textarea from '../../../components/Textarea';
import { CONVENTIONAL, DEFINITIONS, fillLimits, isShariahContract, modeForContract, MUDARABAH, MURABAHAH, SHARIAH_DISCLAIMER, TERM_FIELDS } from '../../../lib/contracts';
import { formatPercent, toNumber } from '../../../lib/format';
import { useModePreview } from '../../../lib/mode';

const PURPOSE_ICONS = { start_up: 'rocket-launch', growth: 'trend-up', education: 'graduation-cap', personal: 'user' };

// Each contract's own terms. The others are left out of the request, so a Shariah request
// never sends a rate (and the server would refuse one).
const TERMS_BY_CONTRACT = {
    [CONVENTIONAL]: ['interest_rate_percent'],
    [MURABAHAH]: ['asset_description', 'markup_amount'],
    [MUDARABAH]: ['investor_profit_share_percent'],
};
const ALL_TERMS = Object.values(TERMS_BY_CONTRACT).flat();

/**
 * A new financing request (PB-13) with the borrower's financial information and optional MMA
 * credit report (PB-14), sent in one go. Every field the risk model uses is required; the
 * server says what is missing next to each field.
 *
 * Picking Murabahah or Mudarabah puts the page in Shariah mode straight away (README, "Modes").
 */
export default function CampaignCreate({ canSubmit, blockedReason, isSme, declaredAnnualIncome, purposes, tenures, housing, employmentYears, limits, maxBytes, tooLargeMessage }) {
    const form = useForm({
        title: '',
        purpose: '',
        description: '',
        business_background: '',
        amount_requested: null,
        tenure_months: '',
        contract_type: '',
        interest_rate_percent: '',
        asset_description: '',
        markup_amount: null,
        investor_profit_share_percent: '',
        annual_income: toNumber(declaredAnnualIncome),
        monthly_debt_repayments: null,
        employment_years: '',
        housing: '',
        open_credit_lines: '',
        late_payments_2y: '',
        public_records: '',
        credit_report: null,
    });

    const contract = form.data.contract_type;
    useModePreview(contract ? modeForContract(contract) : null);

    if (!canSubmit) {
        return (
            <Alert
                tone="warning"
                title="You can’t submit a request yet"
                action={
                    <Button href="/borrower" variant="secondary" size="sm">
                        See your verification steps
                    </Button>
                }
            >
                {blockedReason}
            </Alert>
        );
    }

    function submit(event) {
        event.preventDefault();
        const keep = TERMS_BY_CONTRACT[contract] ?? [];
        form.transform((data) => {
            const sent = { ...data };
            ALL_TERMS.filter((term) => !keep.includes(term)).forEach((term) => delete sent[term]);
            return sent;
        });
        // forceFormData: multipart, which the optional file upload needs.
        form.post('/borrower/campaigns', { forceFormData: true, preserveScroll: true });
    }

    const text = (name) => ({
        id: name,
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value),
        error: form.errors[name],
    });
    const money = (name) => ({
        id: name,
        value: form.data[name],
        onValueChange: (amount) => form.setData(name, amount),
        error: form.errors[name],
    });
    // Whole numbers only (counts): anything but digits is dropped as it is typed.
    const count = (name) => ({
        ...text(name),
        inputMode: 'numeric',
        autoComplete: 'off',
        onChange: (e) => form.setData(name, e.target.value.replace(/\D/g, '')),
        controlClassName: 'max-w-40',
    });
    const field = (contractType, name) => TERM_FIELDS[contractType][name];
    const errorCount = Object.keys(form.errors).length;

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            {errorCount > 0 && (
                <Alert tone="danger" role="alert" title="Your request was not submitted">
                    {errorCount === 1 ? '1 field needs' : `${errorCount} fields need`} attention. Each one says what to fix.
                </Alert>
            )}

            <Card title="Your request" description="Investors see this when your request is approved.">
                <div className="space-y-5">
                    <Input {...text('title')} label="Title" hint="A short name for the request, like “Second delivery van for Malé routes”." maxLength={150} />
                    <RadioCards
                        name="purpose"
                        legend="What is the financing for?"
                        options={purposes.map((purpose) => ({ ...purpose, icon: PURPOSE_ICONS[purpose.value] }))}
                        value={form.data.purpose}
                        onChange={(value) => form.setData('purpose', value)}
                        error={form.errors.purpose}
                    />
                    <Textarea {...text('description')} label="What the money will be used for" hint="At least 30 characters." rows={4} maxLength={5000} />
                    <Textarea
                        {...text('business_background')}
                        label="Business background"
                        optional
                        hint={isSme ? 'What the business does, how long it has run and how it earns.' : 'If the financing is for a business, say what it does.'}
                        rows={3}
                        maxLength={5000}
                    />
                </div>
            </Card>

            <Card title="Amount and repayment">
                <div className="grid gap-5 @bp-sm/shell:grid-cols-2">
                    <MoneyInput
                        {...money('amount_requested')}
                        label={contract === MURABAHAH ? 'Asset cost' : 'Amount needed'}
                        hint={<>From <Money value={limits.minAmount} decimals={0} /> to <Money value={limits.maxAmount} decimals={0} />.</>}
                    />
                    <Select
                        id="tenure_months"
                        label="Tenure"
                        hint="How many months you need to repay."
                        placeholder="Choose a tenure"
                        options={tenures}
                        value={form.data.tenure_months}
                        onChange={(value) => form.setData('tenure_months', value)}
                        error={form.errors.tenure_months}
                    />
                </div>
            </Card>

            <Card
                title="Contract"
                description={
                    <>
                        Choose how investors are paid. <Term definition={DEFINITIONS[MURABAHAH]}>Murabahah</Term> and{' '}
                        <Term definition={DEFINITIONS[MUDARABAH]}>Mudarabah</Term> are Shariah-compliant.
                    </>
                }
            >
                <div className="space-y-5">
                    <ContractTypePicker value={contract} onChange={(value) => form.setData('contract_type', value)} error={form.errors.contract_type} />

                    {contract === CONVENTIONAL && (
                        <PercentInput
                            id="interest_rate_percent"
                            label={field(CONVENTIONAL, 'interest_rate_percent').label}
                            hint={fillLimits(field(CONVENTIONAL, 'interest_rate_percent').hint, limits)}
                            value={form.data.interest_rate_percent}
                            onChange={(value) => form.setData('interest_rate_percent', value)}
                            error={form.errors.interest_rate_percent}
                            className="max-w-xs"
                        />
                    )}

                    {contract === MURABAHAH && (
                        <div className="grid gap-5 @bp-sm/shell:grid-cols-2">
                            <Input {...text('asset_description')} label={field(MURABAHAH, 'asset_description').label} hint={field(MURABAHAH, 'asset_description').hint} maxLength={255} className="@bp-sm/shell:col-span-2" />
                            <MoneyInput {...money('markup_amount')} label={field(MURABAHAH, 'markup_amount').label} hint={field(MURABAHAH, 'markup_amount').hint} />
                            <DescriptionList
                                columns={1}
                                items={[[field(MURABAHAH, 'sale_price').label, <Money key="price" value={(form.data.amount_requested ?? 0) + (form.data.markup_amount ?? 0)} />]]}
                            />
                        </div>
                    )}

                    {contract === MUDARABAH && (
                        <div className="grid gap-5 @bp-sm/shell:grid-cols-2">
                            <PercentInput
                                id="investor_profit_share_percent"
                                label={field(MUDARABAH, 'investor_profit_share_percent').label}
                                hint={fillLimits(field(MUDARABAH, 'investor_profit_share_percent').hint, limits)}
                                value={form.data.investor_profit_share_percent}
                                onChange={(value) => form.setData('investor_profit_share_percent', value)}
                                error={form.errors.investor_profit_share_percent}
                            />
                            <DescriptionList
                                columns={1}
                                items={[[field(MUDARABAH, 'business_share').label, profitKept(form.data.investor_profit_share_percent), true]]}
                            />
                        </div>
                    )}

                    {isShariahContract(contract) && (
                        <Alert tone="info" role="note">
                            {SHARIAH_DISCLAIMER}
                        </Alert>
                    )}
                </div>
            </Card>

            <Card title="Your finances" description="Used to assess your request fairly. Investors don’t see these figures.">
                <div className="grid gap-5 @bp-sm/shell:grid-cols-2">
                    <MoneyInput {...money('annual_income')} label="Annual income" hint="Before tax. Filled in from your profile; change it if it is out of date." />
                    <MoneyInput {...money('monthly_debt_repayments')} label="Monthly repayments on existing loans and cards" hint="Don’t include rent or a home loan. Enter 0 if you have none." />
                    <Select
                        id="employment_years"
                        label={isSme ? 'Time running the business' : 'Time in your current job'}
                        placeholder="Choose how long"
                        options={employmentYears}
                        value={form.data.employment_years}
                        onChange={(value) => form.setData('employment_years', value)}
                        error={form.errors.employment_years}
                    />
                    <RadioCards
                        name="housing"
                        legend="Housing"
                        options={housing}
                        value={form.data.housing}
                        onChange={(value) => form.setData('housing', value)}
                        error={form.errors.housing}
                        className="@bp-sm/shell:col-span-2"
                    />
                </div>
            </Card>

            <Card
                title="Your credit history"
                description="Answer from your MMA Credit Information System report. An administrator checks your answers against the report if you attach it below."
            >
                <div className="space-y-5">
                    <Input {...count('open_credit_lines')} label="How many loans, financing facilities and credit cards do you have open?" hint="Count each one separately. Enter 0 if none." />
                    <Input {...count('late_payments_2y')} label="In the past 2 years, how many times was a repayment 30 or more days late?" hint="Across all your loans and cards. Enter 0 if never." />
                    <Input {...count('public_records')} label="How many court judgments, bankruptcies or debts written off have you had?" hint="Enter 0 if none." />
                </div>
            </Card>

            <Card title="MMA credit report" description="Optional, but it lets the administrator check your credit history answers. Stored privately and encrypted.">
                <FileDropzone
                    id="credit_report"
                    label="Credit report (optional)"
                    hint="PDF, JPG or PNG. Files must be 5 MB or smaller."
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    maxBytes={maxBytes}
                    tooLargeMessage={tooLargeMessage}
                    progress={form.progress?.percentage}
                    onChange={(file) => form.setData('credit_report', file)}
                    error={form.errors.credit_report}
                />
            </Card>

            <Button type="submit" iconRight="arrow-right" loading={form.processing}>
                Submit request for review
            </Button>
        </form>
    );
}

/** "40.0%" for an investors' share of 60, or a dash while the share isn't a valid number. */
function profitKept(investorShare) {
    const share = toNumber(investorShare);
    return share === null || share <= 0 || share >= 100 ? null : formatPercent(100 - share);
}

CampaignCreate.layout = {
    title: 'New financing request',
    description: 'An administrator reviews every request before investors can see it.',
};
