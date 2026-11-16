import '@fontsource-variable/bricolage-grotesque/opsz.css';
import '@fontsource-variable/figtree';
import '@fontsource/ibm-plex-mono/400.css';
import '@fontsource/ibm-plex-mono/500.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import AppRoot from './components/AppRoot';
import AuthLayout from './components/AuthLayout';
import PortalLayout from './components/PortalLayout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    // Laravel sends a page name such as 'Welcome'; load resources/js/pages/Welcome.jsx.
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.jsx', { eager: true });
        return pages[`./pages/${name}.jsx`];
    },

    // Persistent layouts: Inertia keeps these mounted from one page to the next and only swaps
    // the page inside, so the app shell never re-renders from scratch or animates on a page
    // change (PB-51). AppRoot is outermost on every page (mode, tooltips, toasts). A page passes
    // its title to the layout with `Page.layout = { title: '...' }`.
    layout: (name) => {
        if (name.startsWith('Auth/')) {
            return [AppRoot, AuthLayout];
        }
        if (name === 'Welcome' || name.startsWith('Dev/')) {
            return AppRoot;
        }
        return [AppRoot, PortalLayout];
    },

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },

    progress: {
        // The mode's primary colour, so the bar turns green in Shariah mode with everything else.
        color: 'var(--color-primary)',
    },
});
