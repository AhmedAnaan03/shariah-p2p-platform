import Badge from './Badge';

// Every status with its tone, icon and words (README, "Colour"): pending → warning;
// verified, paid, funded → success; rejected, failed, late, defaulted → danger; live → info;
// draft → neutral. The first group is the design system's; the second is the statuses the
// users, kyc_documents and campaigns tables hold that the first group doesn't name.
const STATUSES = {
    draft: ['neutral', 'pencil-simple', 'Draft'],
    pending: ['warning', 'hourglass', 'Awaiting review'],
    'under-review': ['warning', 'magnifying-glass', 'Under review'],
    verified: ['success', 'seal-check', 'Verified'],
    live: ['info', 'storefront', 'Live'],
    funded: ['success', 'check-circle', 'Funded'],
    paid: ['success', 'check', 'Paid'],
    due: ['warning', 'clock', 'Due'],
    late: ['danger', 'warning', 'Late'],
    rejected: ['danger', 'x-circle', 'Rejected'],
    failed: ['danger', 'warning-circle', 'Failed'],
    defaulted: ['danger', 'prohibit', 'Defaulted'],

    pending_verification: ['warning', 'hourglass', 'Pending verification'],
    submitted: ['warning', 'hourglass', 'Awaiting review'],
    suspended: ['danger', 'prohibit', 'Suspended'],
    approved: ['success', 'check-circle', 'Approved'],

    // Campaigns after funding (PB-20) and pledges (PB-19): what an investor sees on a campaign
    // they pledged to. "closed" is a live campaign past its deadline, waiting to be settled.
    closed: ['neutral', 'lock', 'Funding closed'],
    repaying: ['info', 'arrows-clockwise', 'Repaying'],
    // PB-34: a paid-out Mudarabah. Its schedule is planned capital returns, not a debt.
    returning: ['info', 'arrows-clockwise', 'Returning capital'],
    completed: ['success', 'seal-check', 'Fully repaid'],
    // PB-29: a completed Mudarabah. Its capital is settled, never "repaid".
    'capital-settled': ['success', 'seal-check', 'Capital settled'],
    held: ['info', 'lock', 'Held in escrow'],
    refunded: ['neutral', 'arrows-clockwise', 'Refunded'],
    settled: ['success', 'check', 'Settled'],
};

/**
 * A Badge for a status, so the same status always looks and reads the same.
 *
 *   <StatusBadge status="pending" />           amber, hourglass, "Awaiting review"
 *   <StatusBadge status="late">3 days late</StatusBadge>
 */
export default function StatusBadge({ status, size = 'md', children }) {
    const [tone, icon, label] = STATUSES[status] ?? ['neutral', undefined, status];

    return (
        <Badge tone={tone} icon={icon} size={size}>
            {children ?? label}
        </Badge>
    );
}
