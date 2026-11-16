import Badge from './Badge';
import KhatamIcon from './KhatamIcon';

/**
 * The one way to show that a campaign or contract is Shariah-compliant: the khatam and the
 * word "Shariah" (README, "Modes"). It follows the platform palette like everything else, so
 * on a conventional page it is navy-tinted, never a green block on navy; the motif and the word
 * make it recognisable in any mode, for colour-blind users and in greyscale prints.
 *
 * Rule-based, not a formal Shariah certification: pages that show it also carry that
 * disclaimer (PB-33).
 */
export default function ShariahBadge({ size = 'md', label = 'Shariah' }) {
    return (
        <Badge tone="primary" size={size}>
            <KhatamIcon size={size === 'sm' ? 12 : 16} />
            {label}
        </Badge>
    );
}
