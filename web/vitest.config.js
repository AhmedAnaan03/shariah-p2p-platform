import { defineConfig } from 'vitest/config';

// Vitest: unit tests for the plain JS helpers in resources/js/lib (npm test). Kept apart from
// vite.config.js because the Laravel plugin there refuses to start a dev server in CI, and these
// tests need no Laravel or React set-up.
export default defineConfig({
    test: {
        include: ['resources/js/**/*.test.js'],
    },
});
