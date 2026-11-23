import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { applyTheme, onSystemThemeChange, resolveTheme, systemTheme } from '../lib/theme';
import ModeScope from './ModeScope';
import { ToastProvider } from './Toast';
import { TooltipProvider } from './Tooltip';

/**
 * The outermost layout of every page (set in app.jsx). Inertia keeps it mounted from page to
 * page and passes it the page props, so `mode` and `theme` are the shared props from
 * HandleInertiaRequests. It puts the app in that mode and theme and provides tooltips and toasts
 * to everything inside.
 */
export default function AppRoot({ mode, theme, children }) {
    // The page URL: ModeScope re-applies the server's mode whenever it changes.
    const { url } = usePage();

    // The theme (PB-55, PB-57). The head script painted the first page; keep <html> in step when
    // the saved choice changes, and follow the device at once if its setting changes while the
    // page is open: that change is then the most recent one.
    const choice = theme?.choice ?? null;
    const systemAtChoice = theme?.systemAtChoice ?? null;
    useEffect(() => {
        applyTheme(resolveTheme({ choice, systemAtChoice }, systemTheme()));
        return onSystemThemeChange((system) => applyTheme(system));
    }, [choice, systemAtChoice]);

    return (
        <ModeScope mode={mode} pageKey={url}>
            <TooltipProvider delayDuration={300}>
                <ToastProvider>{children}</ToastProvider>
            </TooltipProvider>
        </ModeScope>
    );
}
