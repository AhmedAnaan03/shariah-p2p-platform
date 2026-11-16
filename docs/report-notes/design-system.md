# PB-51 Design system and UI foundation

Feeds Chapter 4, interface design. Issue #58. The design rules themselves are in
`docs/design-system/README.md`; this note covers how they were built into the app.

## What was built

- **Tokens.** The design system's Tailwind CSS v4 theme (`web/resources/css/tailwind-theme.css`)
  replaces Tailwind's default palette: every colour, type size, radius, shadow, easing and
  breakpoint the app may use. Four themes: conventional, Shariah, and a dark version of each
  (dark is included but has no toggle yet).
- **Platform-wide mode.** The whole interface is either conventional (slate and navy) or Shariah
  (moss and muted green). An investor who turns on Shariah-only sees the Shariah palette from the
  first screen. Borrowers, admins and guests are conventional for now (PB-13 and PB-32 will
  change that).
- **Shariah-only switch.** In the investor's top bar (and the mobile menu, and a mode panel on
  the investor home). Switching it cross-fades every colour and the background pattern over
  500 ms, then saves the choice with `PATCH /investor/preferences/shariah-only`.
- **Foundations.** Self-hosted fonts (Bricolage Grotesque, Figtree, IBM Plex Mono), Phosphor
  icons, the khatam icon and the two background textures (lagoon ripples, khatam lattice),
  MVR money formatting, and motion tokens as JS constants.
- **Components.** The shared components were restyled to the spec and 39 new ones added
  (badges, toasts, dialogs, confirm dialog, tooltips, menus, tabs, empty states, skeletons,
  stepper, document rows, file dropzone, stat card, ...), built on Radix primitives where they
  need keyboard and screen-reader behaviour.
- **App shell.** Sidebar (collapsible), top bar, a mobile drawer below 1024 px, and a page
  transition (fade and rise 8 px over 250 ms) where the shell never animates.
- **Every page restyled** (auth, welcome, the three portals, profiles, documents, risk
  acknowledgement, the admin queue and review page). Rejecting an account now asks for
  confirmation, and the admin queue refreshes itself every 30 s, so a row decided by another
  admin leaves with the 200 ms collapse.
- **Design preview** at `/dev/design`, local only: every component in every state, in both modes.

## Key files

| File | What it does |
|---|---|
| `web/resources/css/tailwind-theme.css` | The tokens, mode overrides and `@property` registrations (copied from `docs/design-system/`) |
| `web/resources/css/app.css` | Imports the theme; base layer; `control` focus utility; pattern cross-fade; overlay animations |
| `web/app/Support/PlatformMode.php` | Decides conventional or Shariah for a user, in one place |
| `web/app/Http/Middleware/HandleInertiaRequests.php` | Shares `mode` and `modeSwitch` with every page |
| `web/resources/views/app.blade.php` | Writes `data-mode` / `data-theme` on `<html>` for the first paint |
| `web/app/Http/Controllers/Investor/ShariahPreferenceController.php` | Saves the Shariah-only choice |
| `web/resources/js/app.jsx` | Fonts, and the persistent layout for each kind of page |
| `web/resources/js/lib/mode.js`, `format.js`, `motion.js` | Mode helpers, formatting, motion tokens (unit-tested) |
| `web/resources/js/components/ModeScope.jsx`, `ModeSwitch.jsx`, `AppRoot.jsx` | Keeping `<html data-mode>` in step; the switch and its save |
| `web/resources/js/components/PortalLayout.jsx`, `Sidebar.jsx`, `TopBar.jsx`, `MobileNav.jsx` | The app shell |
| `web/resources/js/pages/Dev/Design.jsx` | The design preview page |
| `web/scripts/check-contrast.mjs` | Token contrast check, all four themes (CI) |
| `web/scripts/screenshots.mjs` | Screenshots plus rendered-page checks (axe-core, overflow, console errors) |

## Design decisions and alternatives

- **Tailwind v4 tokens, not a component kit.** The theme is plain CSS (`@theme`), so the
  existing Vite build needed no change, and utilities like `bg-primary` mean "the current
  mode's primary". shadcn/ui was rejected because the design system forbids its default look.
- **One mode for the whole platform, held in one attribute.** The alternative, passing the mode
  down as a React prop to every component, would need every component to know about it. Instead
  the theme's CSS re-points the same colour variables under `[data-mode="shariah"]`, and every
  component simply uses `bg-primary`. Changing one attribute changes the whole palette.
- **Server writes the first paint.** `PlatformMode` decides the mode once; the root Blade view
  writes it on `<html>` and the same value is shared as an Inertia prop. Setting it from React
  after load would show a flash of the conventional palette for Shariah investors.
- **`@property` cross-fade.** Browsers can't animate a CSS variable unless it is registered with
  a type. Registering each colour token as `<color>` lets the browser fade all of them together
  over 500 ms when `data-mode` changes, with no JavaScript animation. Reduced motion makes it
  instant (1 ms).
- **Optimistic switch.** The palette changes first, then the PATCH saves it; if the save fails
  the palette goes back and a toast explains. The alternative (wait for the server, then change)
  makes the switch feel slow.
- **Persistent layouts.** Inertia v3 keeps layouts mounted between pages, so the sidebar and top
  bar never re-render or animate; only the content inside `PageTransition` does.
- **Self-hosted fonts** (Fontsource) instead of Google Fonts, so the live demo works offline and
  no third party sees visitors.
- **Radix primitives** for dialogs, menus, selects, popovers, tooltips, tabs, switches, checkboxes,
  radios and toasts: focus trapping, Escape, arrow keys and ARIA roles are hard to get right by hand.
- **Shariah-only switch disabled until the profile exists**, rather than moving the column to
  `users` (see `docs/decisions.md`).
- **Tables with many columns stack earlier** (`stack="wide"`, 960 px). The design system says any
  table narrower than 720 px stacks; the admin queue's six columns didn't fit at 724 px, so it
  stacks below 960 px instead, which still satisfies the rule.

## Sample code: the mode wiring

The server decides the mode once (`PlatformMode.php`):

```php
public static function for(?User $user): string
{
    if ($user?->hasRole(Role::INVESTOR) && $user->investorProfile?->shariah_only) {
        return self::SHARIAH;
    }

    return self::CONVENTIONAL;
}
```

The root view paints with it, and every page receives it as a prop (`app.blade.php`,
`HandleInertiaRequests.php`):

```blade
<html lang="..." data-mode="{{ $page['props']['mode'] ?? 'conventional' }}" data-theme="light">
```

The theme re-points the same variables per mode, and registered colours cross-fade
(`tailwind-theme.css`, shortened):

```css
@theme { --color-primary: var(--color-navy-600); }
[data-mode="shariah"] { --color-primary: var(--color-green-600); }

@property --color-primary { syntax: '<color>'; inherits: true; initial-value: #1d6f7d; }
[data-mode], [data-theme] {
  transition-property: --color-primary /* , every other colour token */;
  transition-duration: var(--duration-mode);   /* 500 ms */
  transition-timing-function: var(--ease-mode);
}
```

The switch changes the attribute at once, then saves it (`ModeSwitch.jsx`):

```js
function change(next) {
    const previous = mode;
    setMode(next); // <html data-mode="shariah">: the 500 ms cross-fade starts now

    const fail = (message) => {
        setMode(previous);
        push({ tone: 'danger', title: "We couldn't save Shariah-only mode", children: message });
    };

    router.patch(modeSwitch.url, { shariah_only: next === 'shariah' }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => fail(errors.shariah_only ?? 'Please try again.'),
        onNetworkError: () => fail('Check your connection and try again.'),
    });
}
```

## Accessibility

**Contrast, checked two ways.**

1. *Tokens:* `npm run check:contrast` resolves every colour token in all four themes and checks
   340 foreground/background pairs the components use: text 4.5:1 and controls, icons and focus
   rings 3:1. All pass. The tightest are status text on its own tinted background (4.51:1), so
   status text is only ever placed on its own `-bg` or on white. It runs in CI.
2. *Rendered pages:* `npm run screenshots` runs axe-core (WCAG 2.2 A/AA) on every page at 1440
   and 390 px in both modes, measuring each real text element against what is actually behind it.
   It found five problems the token check could not, all fixed:
   - muted text on a **patterned band** is 4.36:1 where a pattern line runs under it, so text on
     bands now uses `text-secondary` (6.1:1). The token check now models "band + pattern" too
     and lists `text-muted` there as a pair to avoid;
   - the Select's empty-state text ("Choose a sector") used the 3.6:1 placeholder colour; it is
     an instruction, so it now uses `text-muted` (5.2:1);
   - the user menu, the collapsed sidebar logo and a collapsed nav item had accessible names
     that didn't contain their visible text (WCAG 2.5.3);
   - the admin queue scrolled sideways at 820 px.

   Final run: 18 pages × 3 widths × 2 modes, no violations.

   One residual note: `color-text-placeholder` is 3.6:1 by design ("placeholders are examples,
   never instructions"). No page uses a placeholder for anything a user needs to read.

**Focus.** Every focusable element shows a solid 2 px `color-focus-ring` outline with a 2 px
offset on `:focus-visible` (5.8:1 on white in conventional, 5.1:1 in Shariah, 4.8:1 even on a
patterned band). Text fields show a 1.5 px focus-ring border plus a 3 px halo; the border is the
3:1 indicator. There is a "Skip to content" link, dialogs and the mobile menu trap focus and
return it, and the top bar's switch is a real `role="switch"`.

**Reduced motion.** With `prefers-reduced-motion: reduce` the palette change is instant, the
pattern doesn't scale or turn, page changes and removed rows only fade (100 ms), buttons don't
scale, the skeleton doesn't shimmer, and drawers and toasts fade instead of sliding. A unit test
checks these settings.

**Not by colour alone.** Status badges carry an icon and a word; the Shariah badge carries the
khatam and the word "Shariah"; links are underlined; the selected radio card shows its dot; the
stepper's states are written out for screen readers.

## Screenshots

`docs/report-notes/screenshots/design-system/`, named `<page>-<width>-<mode>.png`, for 18 pages
at 1440, 820 and 390 px in conventional and Shariah mode, plus `focus-input.png` and
`focus-button.png`. Only the two focus shots are committed; the rest (about 12 MB) are kept
out of git and regenerated with `npm run screenshots` (see `web/README.md`), which writes them
to the same folder. Good ones for the report:

| Figure | File |
|---|---|
| Investor home, conventional vs Shariah | `investor-portal-1440-conventional.png`, `investor-portal-1440-shariah.png` |
| Same page on a phone | `investor-portal-390-conventional.png` |
| Admin queue, table vs stacked cards | `admin-queue-1440-conventional.png`, `admin-queue-820-conventional.png` |
| Borrower profile form | `borrower-profile-1440-conventional.png` |
| Sign in, Shariah mode, phone | `login-390-shariah.png` |
| Keyboard focus | `focus-input.png`, `focus-button.png` |
| Every component | `design-preview-1440-conventional.png` |

## Unit test tables

### Shariah-only preference route (`Investor/ShariahPreferenceTest.php`)

| TC No | Input | Expected output | Actual output | Result |
|---|---|---|---|---|
| SP-01 | Investor with a profile, `shariah_only: true` | Saved as on; redirect back; "Shariah-only mode is on." | As expected | Pass |
| SP-02 | Investor with Shariah on, `shariah_only: false` | Saved as off; "Shariah-only mode is off." | As expected | Pass |
| SP-03 | Verified investor, `shariah_only: true` | Saved (verification locks KYC details, not preferences) | As expected | Pass |
| SP-04 | Investor A sends `true` with investor B's `user_id` in the body | A's profile on; B's unchanged | As expected | Pass |
| SP-05 | Investor with no profile, `true` | Error "Complete your profile first, then choose Shariah-only mode."; no profile created | As expected | Pass |
| SP-06 | Blank: field missing | "Choose whether Shariah-only mode is on or off."; nothing changes | As expected | Pass |
| SP-07 | Blank: empty string | "Choose whether Shariah-only mode is on or off." | As expected | Pass |
| SP-08 | Invalid: `"yes"` | "Shariah-only mode can only be on or off." | As expected | Pass |
| SP-09 | Invalid: `2` | "Shariah-only mode can only be on or off." | As expected | Pass |
| SP-10 | Guest | Redirect to /login | As expected | Pass |
| SP-11 | Borrower | 403 Forbidden | As expected | Pass |
| SP-12 | Admin | 403 Forbidden | As expected | Pass |

### Platform mode (`PlatformModeTest.php`, `DesignPreviewTest.php`)

| TC No | Input | Expected output | Actual output | Result |
|---|---|---|---|---|
| PM-01 | Guest opens /login | `<html data-mode="conventional" data-theme="light">`; `mode` conventional; no switch | As expected | Pass |
| PM-02 | Investor with Shariah-only on opens /investor | `<html data-mode="shariah">` on first paint; `mode` shariah; switch available | As expected | Pass |
| PM-03 | Investor with Shariah-only off | `data-mode="conventional"` | As expected | Pass |
| PM-04 | Investor with no profile | `mode` conventional; switch present but not available | As expected | Pass |
| PM-05 | Borrower and admin open their portals | Conventional; no switch | As expected | Pass |
| PM-06 | GET /dev/design in tests (guest and admin) | 404; route not registered | As expected | Pass |

### JS helpers (Vitest: `resources/js/lib/*.test.js`)

| TC No | Input | Expected output | Actual output | Result |
|---|---|---|---|---|
| FM-01 | `formatMoney(null)`, `formatMoney('')` (blank) | `—` | As expected | Pass |
| FM-02 | `formatMoney('twelve')`, `NaN`, `Infinity` (invalid) | `—` | As expected | Pass |
| FM-03 | `formatMoney(12500)` | `MVR 12,500.00` | As expected | Pass |
| FM-04 | `formatMoney('12500.5')`, `formatMoney('1,250')` | `MVR 12,500.50`, `MVR 1,250.00` | As expected | Pass |
| FM-05 | `formatMoney(-300)` | `MVR −300.00` (real minus sign) | As expected | Pass |
| FM-06 | `formatMoney(5000, { decimals: 0 })` | `MVR 5,000` (button labels) | As expected | Pass |
| FM-07 | `formatMoney(1200, { sign: true })` | `MVR +1,200.00` | As expected | Pass |
| FM-08 | `formatCompactMoney(12500)`, `(1250000)` | `MVR 12.5K`, `MVR 1.3M` | As expected | Pass |
| FM-09 | `formatPercent('n/a')`; `formatPercent(12.43)` | `—`; `12.4%` | As expected | Pass |
| FD-01 | `formatDate('')`, `formatDate(null)` (blank) | `—` | As expected | Pass |
| FD-02 | `formatDate('not a date')` (invalid) | `—` | As expected | Pass |
| FD-03 | `formatDate('2026-13-45')` (impossible date) | `—` (not rolled over to 14 Feb 2027) | As expected | Pass |
| FD-04 | `formatDate('2026-11-12')` | `12 Nov 2026` | As expected | Pass |
| FD-05 | `formatDate('2026-09-01')` | `1 Sep 2026` (not "Sept") | As expected | Pass |
| FD-06 | `formatTime(9:05)` | `09:05` | As expected | Pass |
| FB-01 | `formatBytes(-1)`; `formatBytes(7549747)` | `—`; `7.2 MB` | As expected | Pass |
| MO-01 | `normaliseMode(undefined)`, `''`, `'Shariah'`, `'islamic'` | `conventional` | As expected | Pass |
| MO-02 | `normaliseMode('shariah')` | `shariah` | As expected | Pass |
| MO-03 | `writeDocumentMode('purple')` | `<html data-mode="conventional">` (never an unknown mode) | As expected | Pass |
| MT-01 | JS `duration` and `ease` vs the CSS tokens | Every value matches `tailwind-theme.css` | As expected | Pass |
| MT-02 | `pageTransition(reduced = true)` | Opacity only, 100 ms or less | As expected | Pass |
| MT-03 | `pressProps(reduced)`, `pressProps(_, disabled)` | No scale | As expected | Pass |

FD-03 first failed (the date rolled over); `toDate()` now checks the parts round-trip.

Full run: `php artisan test` 379 passed; `npm test` 77 passed; `npm run check:contrast` 340 pairs
pass; `npm run screenshots` 108 page checks pass.
