const VARIANTS = {
    info: 'border-sky-200 bg-sky-50 text-sky-900',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    error: 'border-red-200 bg-red-50 text-red-800',
};

/**
 * A coloured message box with an optional bold title.
 *
 *   <Alert variant="warning" title="Your account is pending verification">...</Alert>
 *
 * `role` defaults to "status" (read out politely by screen readers); use "alert" for errors
 * the user must act on now, or "note" for standing information.
 */
export default function Alert({ variant = 'info', title, role = 'status', className = '', children }) {
    return (
        <div role={role} className={`rounded-md border px-4 py-3 text-sm ${VARIANTS[variant]} ${className}`}>
            {title && <p className="font-semibold">{title}</p>}
            {children && <div className={title ? 'mt-1' : ''}>{children}</div>}
        </div>
    );
}
