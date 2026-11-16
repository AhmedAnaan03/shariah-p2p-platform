import { ICONS } from './icons';

/**
 * A Phosphor icon by name (README, "Iconography"): Regular weight, 20 px by default (16 in
 * dense tables and badges, 24 in empty states and file rows), drawn in currentColor.
 *
 *   <Icon name="bell" />                         decorative: hidden from screen readers
 *   <Icon name="warning" label="Warning" />      meaningful on its own: read out
 *
 * Bold weight is only for the active sidebar item.
 */
export default function Icon({ name, size = 20, weight = 'regular', label, className = '' }) {
    const Component = ICONS[name];

    if (!Component) {
        if (import.meta.env.DEV) {
            console.warn(`Icon "${name}" is not in components/icons.js.`);
        }
        return null;
    }

    return (
        <Component
            size={size}
            weight={weight}
            className={`shrink-0 ${className}`}
            {...(label ? { role: 'img', 'aria-label': label } : { 'aria-hidden': true, focusable: false })}
        />
    );
}
