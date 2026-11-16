import * as RadixToast from '@radix-ui/react-toast';
import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react';
import cx from '../lib/cx';
import { duration } from '../lib/motion';
import Button from './Button';
import Icon from './Icon';

const TONES = {
    success: ['check-circle', 'text-success'],
    warning: ['warning', 'text-warning'],
    danger: ['warning-circle', 'text-danger'],
    info: ['info', 'text-info'],
    neutral: ['info', 'text-icon'],
};

/**
 * A short confirmation that slides in from the right and leaves after 5 s, paused while the
 * pointer is over it or it has focus (Radix Toast; announced to screen readers). Errors stay a
 * little longer. Use it for the result of an action ("Your profile has been saved."), not for
 * anything the user must read to carry on: that is an Alert.
 */
export function Toast({ tone = 'success', title, children, action, onClose, duration: ms = 5000, open = true }) {
    const [icon, ink] = TONES[tone] ?? TONES.neutral;

    return (
        <RadixToast.Root
            open={open}
            duration={ms}
            type={tone === 'danger' ? 'foreground' : 'background'}
            onOpenChange={(next) => !next && onClose?.()}
            className="flex w-full animate-toast items-start gap-3 rounded-lg border border-border bg-surface-raised p-4 shadow-md"
        >
            <Icon name={icon} className={cx('mt-0.5', ink)} />
            <div className="min-w-0 flex-1">
                <RadixToast.Title className="text-body font-semibold text-text">{title}</RadixToast.Title>
                {children && <RadixToast.Description className="mt-0.5 text-body-sm text-text-muted">{children}</RadixToast.Description>}
                {action && <div className="mt-2">{action}</div>}
            </div>
            <RadixToast.Close asChild>
                <Button icon="x" variant="ghost" size="sm" aria-label="Dismiss" className="-my-1 -mr-1" />
            </RadixToast.Close>
        </RadixToast.Root>
    );
}

/**
 * The stack of toasts. `inline` renders them in place (the design preview page) instead of the
 * fixed corner viewport.
 */
export function Toaster({ toasts, onDismiss, inline = false }) {
    return (
        <>
            {toasts.map(({ id, ...toast }) => (
                <Toast key={id} {...toast} onClose={() => onDismiss(id)} />
            ))}
            <RadixToast.Viewport
                label="Notifications ({hotkey})"
                className={cx(
                    'flex flex-col gap-3 outline-none',
                    inline ? 'relative w-full max-w-sm' : 'fixed right-0 bottom-0 z-(--z-toast) w-full max-w-sm p-4 sm:p-6',
                )}
            />
        </>
    );
}

const ToastContext = createContext(null);

/**
 * Holds the toasts for the whole app (in AppRoot). A closed toast stays mounted for its exit
 * animation, then is removed.
 */
export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);
    const nextId = useRef(0);

    const dismiss = useCallback((id) => {
        setToasts((list) => list.map((toast) => (toast.id === id ? { ...toast, open: false } : toast)));
        setTimeout(() => setToasts((list) => list.filter((toast) => toast.id !== id)), duration.base * 1000);
    }, []);

    const push = useCallback((toast) => {
        nextId.current += 1;
        const id = `toast-${nextId.current}`;
        setToasts((list) => [...list, { duration: toast.tone === 'danger' ? 8000 : 5000, ...toast, id, open: true }]);
        return id;
    }, []);

    const value = useMemo(() => ({ toasts, push, dismiss }), [toasts, push, dismiss]);

    return (
        <ToastContext.Provider value={value}>
            <RadixToast.Provider swipeDirection="right">
                {children}
                <Toaster toasts={toasts} onDismiss={dismiss} />
            </RadixToast.Provider>
        </ToastContext.Provider>
    );
}

/** { toasts, push, dismiss }. push({ tone, title, children }) shows a toast and returns its id. */
export function useToasts() {
    const context = useContext(ToastContext);
    if (!context) {
        throw new Error('useToasts() needs a <ToastProvider> (AppRoot has one).');
    }
    return context;
}

export default Toast;
