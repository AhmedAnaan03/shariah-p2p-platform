import { Link } from '@inertiajs/react';
import { fundingPercent, investorsText, rateLabel } from '../lib/campaigns';
import cx from '../lib/cx';
import { daysLeftText, formatAmount } from '../lib/format';
import Badge from './Badge';
import ContractBadge from './ContractBadge';
import ProgressBar from './ProgressBar';
import RiskGrade from './RiskGrade';
import Skeleton from './Skeleton';
import StatusBadge from './StatusBadge';

/**
 * A campaign in the marketplace grid (docs/design-system/components/CampaignCard.md): its
 * contract and risk grade, the title, business and purpose, the terms, and funding progress.
 * The whole card links to the campaign page.
 *
 *   <CampaignCard name="Dive compressor" business="Reef Dive Supplies" sector="Tourism and hospitality"
 *       purpose="Growth" contract="murabahah" amount={95000} raised={0} tenure={48} rate="7.5%" rateUnit="in total"
 *       investors={0} daysLeft={5} href="/investor/campaigns/12" />
 *
 * - Follows the platform mode like everything else; a Murabahah or Mudarabah always carries the
 *   ShariahBadge with its contract badge, so it is recognisable on a conventional page too.
 * - The label above `rate` follows the contract: Interest / Markup / Profit share, and
 *   `rateUnit` ("a year", "in total") sits under it, so a markup is never read as a yearly rate.
 * - Terms are three aligned columns of label, figure and unit; figures never wrap.
 * - No `grade` yet: "Not yet scored" (until PB-25).
 * - A link card rises 2 px to shadow-sm over 150 ms on hover (none with reduced motion).
 * - `state="loading"`: a skeleton of the same size.
 */
export default function CampaignCard({ name, business, sector, purpose, contract, grade, amount, raised = 0, tenure, rate, rateUnit, investors = 0, daysLeft, status, href, state }) {
    const frame = 'flex h-full flex-col gap-4 rounded-lg border border-border bg-surface p-5 shadow-xs';

    if (state === 'loading') {
        return (
            <div className={frame} aria-hidden="true">
                <Skeleton width="40%" height={20} />
                <Skeleton width="80%" height={24} />
                <Skeleton width="60%" />
                <Skeleton height={48} radius="md" />
                <Skeleton height={8} />
            </div>
        );
    }

    const percent = fundingPercent(raised, amount);
    const content = (
        <>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <ContractBadge type={contract} size="sm" />
                {grade ? (
                    <RiskGrade grade={grade} size="sm" />
                ) : (
                    <Badge tone="neutral" icon="hourglass" size="sm">
                        Not yet scored
                    </Badge>
                )}
            </div>

            <div className="min-w-0">
                <h3 className="text-h3 text-text">{name}</h3>
                <p className="mt-1 text-body-sm text-text-muted">{[business, sector, purpose].filter(Boolean).join(' · ')}</p>
            </div>

            {/* Three columns on a subgrid with three shared rows: the labels, the figures and the
                units. Every figure stays on one line and every unit sits under its figure, so the
                columns line up on any card width and nothing breaks mid-value. */}
            <dl className="grid grid-cols-3 gap-x-3 border-y border-border py-3">
                <TermColumn label="Target" unit="MVR">
                    {formatAmount(amount, { decimals: 0 })}
                </TermColumn>
                <TermColumn label={rateLabel(contract)} unit={rateUnit}>
                    {rate}
                </TermColumn>
                <TermColumn label="Tenure" unit={tenure === 1 ? 'month' : 'months'}>
                    {tenure}
                </TermColumn>
            </dl>

            <div className="mt-auto space-y-2">
                <ProgressBar value={percent} max={100} label={`${name}: ${percent}% funded`} size="sm" />
                <div className="flex flex-wrap items-center justify-between gap-2 text-body-sm text-text-muted">
                    <span>
                        <span className="font-mono text-text-secondary">{percent}%</span> funded · {investorsText(investors)}
                    </span>
                    {status && status !== 'live' ? (
                        <StatusBadge status={status} size="sm" />
                    ) : (
                        <Badge tone="info" icon="clock" size="sm">
                            {daysLeftText(daysLeft)}
                        </Badge>
                    )}
                </div>
            </div>
        </>
    );

    if (!href) {
        return <article className={frame}>{content}</article>;
    }

    return (
        <Link
            href={href}
            className={cx(
                frame,
                'transition-[translate,box-shadow] duration-(--duration-fast) ease-standard hover:-translate-y-0.5 hover:shadow-sm motion-reduce:hover:translate-y-0',
            )}
        >
            {content}
        </Link>
    );
}

/**
 * One column of the terms block: label, figure, unit, each on its own row of the dl's subgrid.
 * The figure never wraps; a long label or unit wraps by word within its row, and the rows grow
 * together across the three columns.
 */
function TermColumn({ label, unit, children }) {
    return (
        <div className="row-span-3 grid min-w-0 grid-rows-subgrid gap-y-0.5">
            <dt className="text-overline text-text-muted">{label}</dt>
            <dd className="font-mono text-figure whitespace-nowrap text-text">{children}</dd>
            {unit ? <dd className="text-body-sm text-text-muted">{unit}</dd> : <dd aria-hidden="true" />}
        </div>
    );
}
