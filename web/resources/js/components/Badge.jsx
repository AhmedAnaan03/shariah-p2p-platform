const VARIANTS = {
    neutral: 'bg-slate-100 text-slate-700',
    success: 'bg-emerald-100 text-emerald-800',
    warning: 'bg-amber-100 text-amber-900',
    danger: 'bg-red-100 text-red-800',
    info: 'bg-sky-100 text-sky-800',
};

/**
 * A small coloured label, e.g. a role or a document's status.
 */
export default function Badge({ variant = 'neutral', children }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${VARIANTS[variant]}`}>
            {children}
        </span>
    );
}

// Every status the users and kyc_documents tables can hold, with its colour and wording.
const STATUSES = {
    pending_verification: ['warning', 'Pending verification'],
    verified: ['success', 'Verified'],
    rejected: ['danger', 'Rejected'],
    suspended: ['danger', 'Suspended'],
    pending: ['warning', 'Awaiting review'],
    approved: ['success', 'Approved'],
};

/**
 * A Badge for an account or document status, so the same status always looks the same.
 *
 *   <StatusBadge status="pending" />  →  amber "Awaiting review"
 */
export function StatusBadge({ status }) {
    const [variant, label] = STATUSES[status] ?? ['neutral', status];

    return <Badge variant={variant}>{label}</Badge>;
}
