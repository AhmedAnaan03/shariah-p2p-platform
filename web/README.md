# web/: Laravel + Inertia + React + Tailwind

The main web application: Laravel 13 on PHP 8.4+, with React 19 pages rendered through
Inertia.js and styled with Tailwind CSS 4. Data is stored in MySQL 8.

## Requirements

| Tool | Version | macOS install |
|---|---|---|
| PHP | 8.4 or newer | `brew install php` |
| Composer | 2.x | `brew install composer` |
| MySQL | 8.x (not MariaDB) | `brew install mysql@8.4` then `brew services start mysql@8.4` |
| Node.js | 20.19 or newer | `brew install node` |

`mysql@8.4` is keg-only, so add its client to your PATH (for example in `~/.zshrc`):

```sh
export PATH="/opt/homebrew/opt/mysql@8.4/bin:$PATH"
```

## First-time setup

1. Create the databases and a local user (one time):

   ```sh
   mysql -u root <<'SQL'
   CREATE DATABASE shariah_p2p CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE shariah_p2p_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'shariah_p2p'@'localhost' IDENTIFIED BY 'choose-a-local-password';
   GRANT ALL PRIVILEGES ON shariah_p2p.* TO 'shariah_p2p'@'localhost';
   GRANT ALL PRIVILEGES ON shariah_p2p_testing.* TO 'shariah_p2p'@'localhost';
   SQL
   ```

2. Install dependencies and configure the app:

   ```sh
   cd web
   composer install
   npm install
   cp .env.example .env        # then set DB_PASSWORD to the password chosen above
   php artisan key:generate
   php artisan migrate
   ```

3. Raise PHP's upload limits (one time). KYC documents may be up to **5 MB**, but PHP's
   defaults (`upload_max_filesize = 2M`, `post_max_size = 8M`) would refuse a larger file
   before Laravel sees it, and the user would get a vague "could not be uploaded" error instead
   of "Files must be 5 MB or smaller". Find the loaded `php.ini` with `php --ini` (Homebrew:
   `/opt/homebrew/etc/php/<version>/php.ini`) and set:

   ```ini
   upload_max_filesize = 10M
   post_max_size = 12M
   ```

   Both are deliberately above 5 MB, so a 5–10 MB file reaches Laravel and gets the app's own
   message. Check with `php -i | grep -E "upload_max_filesize|post_max_size"`, and restart
   `php artisan serve` after changing them. The 5 MB rule itself is
   `KycDocument::MAX_KILOBYTES`; the tests don't depend on `php.ini`.

## Running locally

```sh
npm run dev            # terminal 1: Vite dev server (hot reload for React/Tailwind)
php artisan serve      # terminal 2: Laravel at http://localhost:8000
```

Or run `npm run build` once and only `php artisan serve`.

On a developer machine (`APP_ENV=local`) the design preview at
<http://localhost:8000/dev/design> shows every shared component in every state, with a
conventional / Shariah mode toggle. It is never registered in tests or production.

## Tests

```sh
php artisan test
```

Tests run against the separate `shariah_p2p_testing` MySQL database (set in `phpunit.xml`),
which is wiped and re-migrated by tests that use `RefreshDatabase`. The development database
is never touched by tests.

```sh
npm test                 # Vitest: the JS helpers in resources/js/lib (formatting, mode, motion tokens, contract wording)
npm run check:contrast   # every text, control and focus-ring colour pair, all four themes, WCAG AA
```

CI runs all three.

### Accessibility checks and report screenshots

The project's testing and evidence policy:

- **During development, no screenshots.** When an item changes pages or styling, run the
  axe-only check on just the pages it touched, in light conventional mode, and fix anything it
  finds. Nothing is saved.
- **Once, in Sprint 5 (PB-56), the full run:** every page at three widths in all four themes
  (conventional and Shariah, each light and dark), with the report screenshots saved outside
  the repository. Report notes don't include screenshots before then.

Both use `scripts/screenshots.mjs`, which signs in as the demo accounts in headless Chrome
and, for every page and width, fails on a console error, an uncaught exception or a
horizontal scrollbar, and runs axe-core's WCAG 2.2 AA rules (including the contrast of the
rendered text) at 1440 and 390 px. It uses its own throwaway database, so your development data
is untouched:

```sh
mysql -u root -e "CREATE DATABASE IF NOT EXISTS shariah_p2p_screenshots; GRANT ALL ON shariah_p2p_screenshots.* TO 'shariah_p2p'@'localhost';"
DB_DATABASE=shariah_p2p_screenshots php artisan migrate:fresh --seed --seeder=ScreenshotSeeder
DB_DATABASE=shariah_p2p_screenshots php artisan serve --port=8010    # terminal 1
npm run build                                                         # terminal 2, then:

# Per item: axe-only, only the pages it touched, light conventional, no screenshots
npm run check:a11y -- onboarding-borrower-profile admin-queue

# Sprint 5 (PB-56): every page, all four themes, screenshots outside the repo
npm run screenshots -- --out=$HOME/amanah-evidence/screenshots
```

Options: page names limit any run (required for `check:a11y`; a wrong name lists the valid
ones); `--theme=conventional,shariah,conventional-dark,shariah-dark` picks the themes (default
light conventional for `check:a11y`, all four for `screenshots`); `--out=<dir>` sets where
screenshots go. The full run also checks the Shariah-only switch and theme toggle round trips
and the no-flash first paint (or name `mode-switch` / `theme-toggle` to run them alone).
Re-seed after pulling new migrations. It needs Google Chrome (`CHROME_PATH` to point
elsewhere). Without `--out`, screenshots go to the git-ignored
`docs/report-notes/screenshots/design-system/`, except `focus-input.png` and
`focus-button.png`, which are tracked for the PB-51 report notes.

## Where things live

| Path | Purpose |
|---|---|
| `routes/web.php` | URL → controller mapping |
| `app/Http/Controllers/` | Controllers; they return `Inertia::render('PageName', [props])` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Inertia middleware; data shared with every page goes in `share()` |
| `resources/views/app.blade.php` | The single HTML shell every page is mounted into |
| `resources/js/app.jsx` | Boots React and maps page names to files in `resources/js/pages/` |
| `resources/js/pages/` | One React component per page (e.g. `Welcome.jsx`) |
| `resources/js/components/` | Shared UI components; pages build their forms and panels from these (see below) |
| `resources/css/app.css` | Tailwind entry point: imports the theme, base layer, control and animation utilities |
| `resources/css/tailwind-theme.css` | The design-system tokens (copied from `docs/design-system/`; edit it there) |
| `resources/js/lib/` | Plain JS helpers: formatting (`format.js`), platform mode (`mode.js`), motion tokens (`motion.js`), contract types and their wording (`contracts.js`) |
| `tests/Feature/` | HTTP-level tests run by `php artisan test` |
| `storage/app/private/kyc/` | Uploaded KYC documents and MMA credit reports, encrypted, never served directly (PB-09, PB-14) |
| `app/Support/RiskFeatures.php` | A stored request → the risk model's features; checked against `risk-service/tests/fixtures/feature_parity.json` (PB-14) |
| `app/Services/Campaign*Service.php` | Submitting a request (PB-13, PB-14) and an administrator's approve / reject / suspend decision (PB-15) |

## UI components

The design system in `docs/design-system/` is the source of truth for how the UI looks and
moves; read its `README.md` before building a page or component. Every page is built from the
components in `resources/js/components/`, which follow the names and props in
`docs/design-system/component-api.d.ts`. In a page, Tailwind classes are only for layout
(flex/grid, spacing, widths); colours, borders, shadows and control styling live in a
component, and only design tokens are used (no raw hex colours, no arbitrary pixel values,
no emoji). If a page needs something no component offers, add or extend a component.

Layouts are persistent (set per page name in `app.jsx`): a page passes its title with
`Page.layout = { title: '...' }`.

| Component | Use |
|---|---|
| `Input`, `Select`, `Textarea`, `FileDropzone` | Labelled form controls with a hint and the server's error (built on `Field`, `TextInput`) |
| `MoneyInput`, `PercentInput` | Amounts in MVR (formatted when the box loses focus) and percentages, in Plex Mono (PB-13) |
| `ContractTypePicker` | Conventional / Murabahah / Mudarabah as cards; the Shariah ones carry the `ShariahBadge` (PB-13) |
| `Checkbox`, `RadioGroup`, `RadioCards`, `Switch` | Tick box; plain radio list; options drawn as cards; on/off switch |
| `Button`, `TextLink` | Buttons (also as Inertia links, e.g. sign-out with `method="post"`); in-text links |
| `Alert`, `Toast` / `useToasts`, `StatusMessage`, `Spinner`, `Skeleton`, `EmptyState` | Feedback, loading and empty lists |
| `Badge`, `StatusBadge`, `RoleBadge`, `ShariahBadge`, `ContractBadge` | Small labels, always with an icon and words |
| `Card`, `StatCard`, `PageBand`, `PageHeading`, `DescriptionList`, `BulletList`, `Table`, `OnboardingStepper`, `DocumentList` / `DocumentRow`, `Money`, `Avatar` | Panels and read-only content |
| `Dialog`, `ConfirmDialog`, `Tooltip`, `Term`, `Popover`, `DropdownMenu`, `Tabs` | Overlays and navigation (Radix primitives) |
| `Icon`, `KhatamIcon`, `BackgroundPattern`, `Wordmark` | Phosphor icons by name (`icons.js`), the khatam, the mode's texture, the brand |
| `AppRoot`, `ModeScope`, `ModeSwitch`, `ModePanel` | The platform mode (conventional / Shariah) and the investor's Shariah-only switch |
| `AuthLayout`, `PortalLayout`, `Sidebar`, `TopBar`, `MobileNav`, `SiteHeader`, `PageTransition` | Page frames and the app shell |
