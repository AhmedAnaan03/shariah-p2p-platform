import { useForm } from '@inertiajs/react';
import Alert from '../../../components/Alert';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import DescriptionList from '../../../components/DescriptionList';
import FileDropzone from '../../../components/FileDropzone';
import Money from '../../../components/Money';
import MoneyInput from '../../../components/MoneyInput';
import TextLink from '../../../components/TextLink';
import { formatMoney } from '../../../lib/format';
import { REPORT_TEXT as TEXT, reportPreview, resultLabel } from '../../../lib/mudarabah';
import { toCents } from '../../../lib/wallet';

/**
 * A Mudarabah business reports one period (PB-29): its revenue and costs (so its profit or loss),
 * the capital it is returning, and an optional supporting document. As the figures are typed, a
 * preview shows what approval would do: a profit first restores any genuine losses the investors
 * bore earlier, and what is left is shared at the agreed ratio (lib/mudarabah.js mirrors the
 * server's rule; the server works it out again when the report is approved). No money moves until
 * an administrator approves the report.
 */
export default function ReportCreate({ campaign, period, capital, investorShareBp, ratio, note, balance, storeUrl, maxBytes, tooLargeMessage, back }) {
    const form = useForm({ revenue: null, costs: null, capital_returned: period.suggestedCapital, document: null });
    const preview = reportPreview({
        revenue: form.data.revenue,
        costs: form.data.costs,
        capitalReturned: form.data.capital_returned,
        unrestored: capital.unrestored,
        investorShareBp,
    });
    const money = (cents) => <Money value={(cents / 100).toFixed(2)} />;
    const short = preview.payment > toCents(balance);

    function submit(event) {
        event.preventDefault();
        // forceFormData: multipart, which the optional document needs.
        form.post(storeUrl, { forceFormData: true, preserveScroll: true });
    }

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            <TextLink href={back.url} icon="arrow-left">
                {back.label}
            </TextLink>

            <Card title={`${period.label}: planned date ${period.dateLabel}`} description={note}>
                <DescriptionList
                    columns={4}
                    items={[
                        ['Capital still invested', <Money key="outstanding" value={capital.outstanding} />],
                        ['Planned capital return', <Money key="planned" value={period.plannedCapital} />],
                        ['Losses still to restore', <Money key="unrestored" value={capital.unrestored} />],
                        ['Profit-sharing ratio', ratio],
                    ]}
                />
            </Card>

            {period.final && (
                <Alert tone="info" role="note" title={TEXT.finalTitle}>
                    {TEXT.final(formatMoney(capital.outstanding))}
                </Alert>
            )}

            <Card title={TEXT.figuresTitle} description={TEXT.figuresNote}>
                <div className="grid gap-5 @bp-md/shell:grid-cols-2">
                    <MoneyInput id="revenue" label="Revenue" value={form.data.revenue} onValueChange={(value) => form.setData('revenue', value)} error={form.errors.revenue} />
                    <MoneyInput id="costs" label="Costs" value={form.data.costs} onValueChange={(value) => form.setData('costs', value)} error={form.errors.costs} />
                    <MoneyInput
                        id="capital_returned"
                        label="Capital you are returning"
                        hint={`Planned: ${formatMoney(period.plannedCapital)}. At most ${formatMoney(capital.outstanding)}.`}
                        value={form.data.capital_returned}
                        onValueChange={(value) => form.setData('capital_returned', value)}
                        error={form.errors.capital_returned}
                    />
                </div>
            </Card>

            <Card title={TEXT.previewTitle} description={TEXT.previewNote}>
                <div className="space-y-4" aria-live="polite">
                    {preview.ready ? (
                        <DescriptionList
                            columns={3}
                            items={[
                                [resultLabel(preview.profit), money(Math.abs(preview.profit))],
                                ['Capital returned', money(preview.capital)],
                                ...(preview.profit > 0
                                    ? [
                                          ['Earlier losses restored', money(preview.restored)],
                                          ['Investors’ profit share', money(preview.investorsProfit)],
                                          ['Your profit share', money(preview.businessProfit)],
                                      ]
                                    : []),
                                ['Paid to investors from your wallet', money(preview.payment)],
                            ]}
                        />
                    ) : (
                        <p className="text-body-sm text-text-secondary">{TEXT.previewEmpty}</p>
                    )}
                    {preview.isLoss && (
                        <Alert tone="info" role="note">
                            {TEXT.lossNote}
                        </Alert>
                    )}
                    {short && (
                        <Alert tone="warning" role="note" action={<TextLink href="/borrower/wallet">Top up your wallet</TextLink>}>
                            {TEXT.walletShort(formatMoney(balance))}
                        </Alert>
                    )}
                </div>
            </Card>

            <Card title={TEXT.documentTitle} description={TEXT.documentNote}>
                <FileDropzone
                    id="document"
                    label="Supporting document (optional)"
                    hint="PDF, JPG or PNG. Files must be 5 MB or smaller."
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    maxBytes={maxBytes}
                    tooLargeMessage={tooLargeMessage}
                    progress={form.progress?.percentage}
                    onChange={(file) => form.setData('document', file)}
                    error={form.errors.document}
                />
            </Card>

            <Button type="submit" iconRight="arrow-right" loading={form.processing}>
                {TEXT.submit}
            </Button>
        </form>
    );
}

ReportCreate.layout = (props) => ({
    title: `Business report: ${props.period.label}`,
    description: props.campaign.title,
    breadcrumbs: [{ label: props.back.crumb, href: props.back.url }, { label: `Report ${props.period.label.toLowerCase()}` }],
});
