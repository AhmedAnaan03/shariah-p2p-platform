import { usePage } from '@inertiajs/react';
import ModeScope from './ModeScope';
import { ToastProvider } from './Toast';
import { TooltipProvider } from './Tooltip';

/**
 * The outermost layout of every page (set in app.jsx). Inertia keeps it mounted from page to
 * page and passes it the page props, so `mode` is the shared prop from HandleInertiaRequests.
 * It puts the app in that mode and provides tooltips and toasts to everything inside.
 */
export default function AppRoot({ mode, children }) {
    // The page URL: ModeScope re-applies the server's mode whenever it changes.
    const { url } = usePage();

    return (
        <ModeScope mode={mode} pageKey={url}>
            <TooltipProvider delayDuration={300}>
                <ToastProvider>{children}</ToastProvider>
            </TooltipProvider>
        </ModeScope>
    );
}
