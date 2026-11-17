import { CONTRACTS } from '../lib/contracts';
import Badge from './Badge';
import ShariahBadge from './ShariahBadge';

/**
 * A campaign's contract type (component-api.d.ts, ContractBadge): its icon and name in a
 * neutral badge, followed by the ShariahBadge for Murabahah and Mudarabah, so a campaign's
 * Shariah status is always the badge with the word "Shariah" (README, "Modes").
 *
 *   <ContractBadge type="murabahah" size="sm" />
 */
export default function ContractBadge({ type, size = 'md' }) {
    const contract = CONTRACTS.find((c) => c.value === type);

    if (!contract) {
        return null;
    }

    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            <Badge tone="neutral" icon={contract.icon} size={size}>
                {contract.label}
            </Badge>
            {contract.shariah && <ShariahBadge size={size} />}
        </span>
    );
}
