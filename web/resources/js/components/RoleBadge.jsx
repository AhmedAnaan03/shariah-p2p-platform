import Badge from './Badge';

const ROLES = {
    investor: ['hand-coins', 'Investor'],
    borrower: ['briefcase', 'Borrower'],
    admin: ['shield-check', 'Administrator'],
};

/**
 * A person's role, with its icon. Neutral, so it never reads as a status.
 *
 *   <RoleBadge role="borrower" />
 */
export default function RoleBadge({ role, size = 'md' }) {
    const [icon, label] = ROLES[role] ?? ['user', role];

    return (
        <Badge tone="neutral" icon={icon} size={size}>
            {label}
        </Badge>
    );
}
