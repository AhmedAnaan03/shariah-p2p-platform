import { defineConfig } from 'vitest/config';

// Vitest: unit tests for the plain JS helpers in resources/js/lib (npm test). Kept apart from
// vite.config.js because the Laravel plugin there refuses to start a dev server in CI, and these
// tests need no Laravel set-up. Component tests (*.test.jsx, since PB-17) render to an HTML
// string with react-dom/server, so they need no browser either; real layout at each width is
// checked by scripts/screenshots.mjs.
export default defineConfig({
    test: {
        include: ['resources/js/**/*.test.{js,jsx}'],
    },
});
