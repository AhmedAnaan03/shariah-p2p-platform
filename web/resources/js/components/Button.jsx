import { Link } from '@inertiajs/react';

const VARIANTS = {
    primary: 'bg-emerald-700 text-white hover:bg-emerald-800 focus:ring-emerald-300',
    secondary: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 focus:ring-slate-200',
    danger: 'bg-red-700 text-white hover:bg-red-800 focus:ring-red-300',
};

const SIZES = {
    md: 'px-4 py-2',
    sm: 'px-3 py-1.5',
};

/**
 * The one button style for the whole app.
 *
 *   <Button type="submit" processing={form.processing}>Save</Button>   a form button
 *   <Button href="/borrower/profile">Complete profile</Button>          a link that looks like a button
 *   <Button href="/logout" method="post" variant="secondary">Sign out</Button>
 *
 * `processing` disables the button while a form is being sent, so it can't be double-submitted.
 * With `href` it renders an Inertia <Link>; `method="post"` makes that link send a POST (with
 * the CSRF token) and render as a real <button>.
 */
export default function Button({
    variant = 'primary',
    size = 'md',
    type = 'button',
    processing = false,
    disabled = false,
    fullWidth = false,
    href,
    method,
    className = '',
    children,
    ...props
}) {
    const classes = `inline-flex items-center justify-center rounded-md text-sm font-semibold shadow-sm focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-60 ${VARIANTS[variant]} ${SIZES[size]} ${
        fullWidth ? 'w-full' : ''
    } ${className}`;

    if (href) {
        return (
            <Link href={href} method={method} as={method && method !== 'get' ? 'button' : 'a'} className={classes} {...props}>
                {children}
            </Link>
        );
    }

    return (
        <button type={type} disabled={processing || disabled} className={classes} {...props}>
            {children}
        </button>
    );
}
