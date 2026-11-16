import { Link } from '@inertiajs/react';
import { motion } from 'motion/react';
import cx from '../lib/cx';
import { pressProps, useReducedMotion } from '../lib/motion';
import Icon from './Icon';
import Spinner from './Spinner';

const MotionLink = motion.create(Link);

const VARIANTS = {
    primary: 'bg-primary text-primary-fg shadow-xs hover:bg-primary-hover active:bg-primary-pressed',
    secondary: 'border border-border-strong bg-surface text-text shadow-xs hover:bg-surface-subtle',
    ghost: 'text-text-secondary hover:bg-surface-subtle hover:text-text',
    danger: 'bg-danger text-primary-fg shadow-xs hover:brightness-90 active:brightness-80',
    link: 'text-link underline-offset-4 hover:underline',
};

// sm 32 / md 40 / lg 48 px tall; icon-only buttons are square. 44 px on touch screens.
const SIZES = {
    sm: 'h-(--control-sm) px-3 text-label font-semibold pointer-coarse:min-h-(--touch-target)',
    md: 'h-(--control-md) px-4 text-body font-semibold pointer-coarse:min-h-(--touch-target)',
    lg: 'h-(--control-lg) px-5 text-body-lg font-semibold',
};

const ICON_ONLY = {
    sm: 'w-(--control-sm) pointer-coarse:min-w-(--touch-target)',
    md: 'w-(--control-md) pointer-coarse:min-w-(--touch-target)',
    lg: 'w-(--control-lg)',
};

/**
 * Starts an action (docs/design-system/components/Button.md). The label names the action
 * and, where there is one, the amount: "Pledge MVR 5,000", "Approve borrower".
 *
 *   <Button type="submit" loading={form.processing}>Save profile</Button>
 *   <Button href="/borrower/profile" variant="secondary" iconRight="arrow-right">Complete profile</Button>
 *   <Button icon="x" aria-label="Close" variant="ghost" />          icon-only: always pass aria-label
 *   <Button href="/logout" method="post" variant="secondary">Sign out</Button>
 *
 * One primary button per region. Danger is for irreversible actions and is followed by a
 * ConfirmDialog. While `loading` (or the older `processing`) the button is disabled, keeps its
 * width and label, shows a spinner in place of the icon, and sets aria-busy. With `href` it is
 * an Inertia <Link>; `method="post"` makes that link send a POST and render as a <button>.
 */
export default function Button({
    variant = 'primary',
    size = 'md',
    type = 'button',
    iconLeft,
    iconRight,
    icon,
    loading = false,
    processing = false,
    disabled = false,
    fullWidth = false,
    href,
    method,
    className = '',
    children,
    ...props
}) {
    const reduced = useReducedMotion();
    const busy = loading || processing;
    const isDisabled = disabled || busy;
    const iconSize = size === 'sm' ? 16 : 20;

    const classes = cx(
        'inline-flex items-center justify-center gap-2 rounded-md whitespace-nowrap select-none',
        'transition-[background-color,color,filter,box-shadow] duration-(--duration-fast) ease-standard',
        'disabled:cursor-not-allowed disabled:opacity-(--opacity-disabled) aria-disabled:cursor-not-allowed aria-disabled:opacity-(--opacity-disabled)',
        VARIANTS[variant],
        variant === 'link' ? 'h-auto p-0 text-body font-medium' : SIZES[size],
        icon && variant !== 'link' && cx(ICON_ONLY[size], 'px-0'),
        fullWidth && 'w-full',
        className,
    );

    const content = icon ? (
        busy ? <Spinner size={iconSize} /> : <Icon name={icon} size={iconSize} />
    ) : (
        <>
            {busy ? <Spinner size={iconSize} /> : iconLeft && <Icon name={iconLeft} size={iconSize} />}
            {children}
            {iconRight && <Icon name={iconRight} size={iconSize} />}
        </>
    );

    const motionProps = variant === 'link' ? {} : pressProps(reduced, isDisabled);

    if (href) {
        const asButton = method && method !== 'get';
        return (
            <MotionLink
                href={href}
                method={method}
                as={asButton ? 'button' : 'a'}
                type={asButton ? 'button' : undefined}
                className={classes}
                aria-busy={busy || undefined}
                {...motionProps}
                {...props}
            >
                {content}
            </MotionLink>
        );
    }

    return (
        <motion.button type={type} disabled={isDisabled} aria-busy={busy || undefined} className={classes} {...motionProps} {...props}>
            {content}
        </motion.button>
    );
}
