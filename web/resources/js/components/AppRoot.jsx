import ModeScope from './ModeScope';
import { ToastProvider } from './Toast';
import { TooltipProvider } from './Tooltip';

/**
 * The outermost layout of every page (set in app.jsx). Inertia keeps it mounted from page to
 * page and passes it the page props, so `mode` is the shared prop from HandleInertiaRequests.
 * It puts the app in that mode and provides tooltips and toasts to everything inside.
 */
export default function AppRoot({ mode, children }) {
    return (
        <ModeScope mode={mode}>
            <TooltipProvider delayDuration={300}>
                <ToastProvider>{children}</ToastProvider>
            </TooltipProvider>
        </ModeScope>
    );
}
