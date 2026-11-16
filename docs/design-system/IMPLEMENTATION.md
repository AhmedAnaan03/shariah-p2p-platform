# Implementing the Amanah design system in the app

This folder is the Amanah P2P design system exported from Claude Design, for the Laravel + Inertia + React + Tailwind app in `web/`. It is the source of truth for how the UI looks and moves.

| File | What it is |
|---|---|
| `README.md` | The design rules: principles, voice, modes, colour roles, layout, type, spacing, shape, icons, motion, charts, accessibility, do and don't. Read it in full before building anything. |
| `tailwind-theme.css` | Every token as a Tailwind CSS v4 `@theme` block, the Shariah and dark overrides, and the `@property` rules that cross-fade the palette when the mode changes. Compiles cleanly with Tailwind 4. |
| `component-api.d.ts` | The props of every designed component. A reference for names and props, not code to import. |
| `components/*.md` | Detailed specs for the six key components: Button, CampaignCard, ModeScope, RiskExplanation, Sidebar, TopBar. |
| `patterns/` | The conventional wave tile, the Shariah khatam tile and the 16 px khatam icon (SVG). |

The live design system with previews of every component is the "Amanah P2P" Design System artifact on claude.ai. When a detail isn't written down here, ask the developer for a screenshot of that component rather than guessing.

## 1. Setup

- Confirm `web/` uses Tailwind CSS v4 (the `@tailwindcss/vite` plugin and `@import "tailwindcss"`). If it's on v3, stop and say so before converting anything.
- Copy `tailwind-theme.css` to `web/resources/css/tailwind-theme.css` and import it right after `@import "tailwindcss";` in `web/resources/css/app.css`.
- Copy `patterns/*.svg` to `web/resources/images/patterns/` and reference them through Vite imports.
- Install, pinned in `package.json`:
  - `@fontsource-variable/bricolage-grotesque`, `@fontsource-variable/figtree` and `@fontsource/ibm-plex-mono` (weights 400 and 500). These self-host the fonts so the live demo works without internet. The theme's font stacks already use their family names.
  - `@radix-ui/react-*` primitives for dialog, dropdown-menu, popover, tabs, tooltip, select, switch, checkbox, radio-group, accordion, toast and slot.
  - `motion` (Framer Motion) for animation, and `@phosphor-icons/react` for icons.
  - `recharts` only when the first chart is built (not in this item).
- Base layer in `app.css`:
  - `body` uses `bg-bg text-text font-sans text-body` with antialiasing.
  - `h1`, `h2` and `h3` (and the `text-h1`, `text-h2`, `text-h3` and `text-display` utilities) also need `font-display`. The theme's type tokens set size, line height, tracking and weight, but not the family.
  - `text-figure*` and `text-code` pair with `font-mono`, which already turns on tabular figures.

## 2. Mode wiring (conventional ⇄ Shariah)

The mode is platform-wide (see `README.md`, "Modes").

- Put `data-mode="conventional|shariah"` and `data-theme="light"` on `<html>` in `app.blade.php`, using the value the server already knows, so the first paint is in the right palette with no flash.
- Share `mode` as an Inertia prop from `HandleInertiaRequests`:
  - **Investors:** `shariah` when `investor_profiles.shariah_only` is true.
  - **Everyone else:** `conventional` for now. The borrower request flow (PB-13) and the admin Shariah settings (PB-32) switch it later.
- Build `usePlatformMode()` and `ModeScope` to keep `<html data-mode>` in sync with that prop.
- Build `ModeSwitch` (top bar) for investors. Switching it:
  1. Updates `data-mode` immediately, so the 500 ms cross-fade plays.
  2. Persists `shariah_only` through a small authenticated PATCH route, with a feature test.
  
  Hiding non-Shariah campaigns stays in PB-31.
- Dark theme tokens are included, but there's no dark toggle in this item. Keep `data-theme="light"`.

## 3. Build order

1. **Foundations:**
   - theme, fonts and the base layer
   - `ModeScope` / `usePlatformMode`
   - `Icon` (a thin wrapper over Phosphor), `Money` (MVR formatting, tabular, muted code), `KhatamIcon`, `BackgroundPattern` (both tiles, cross-faded by mode)
   - a `motion.js` with the duration and easing tokens as JS constants and a `useReducedMotion` guard
2. **Restyle the existing shared components in `resources/js/components` to the spec,** keeping their file names and props where possible so pages don't break:
   - Button, Field / Input / Select / Textarea, Checkbox, Alert, Badge, Card
   - FileUpload → `FileDropzone` (5 MB hint, progress, error)
   - Add `StatusBadge`, `RoleBadge`, `ShariahBadge`, `Spinner`, `Skeleton`, `Toast` / `useToasts`, `Dialog`, `ConfirmDialog`, `Tooltip`, `Term`, `DescriptionList`, `DocumentRow`, `OnboardingStepper`, `EmptyState`, `Avatar`, `DropdownMenu`, `Tabs`.
3. **App shell:**
   - `Sidebar` (wordmark, nav per portal, active state, collapse), `TopBar` (title, ModeSwitch for investors, bell, user menu), `MobileNav` below 1024 px
   - one `PortalLayout` per role
   - an Inertia page transition: fade and rise 8 px over 250 ms, with the shell not animating
4. **Restyle every existing page:**
   - auth pages
   - the three portal homes, with the onboarding checklist on the borrower and investor portals
   - borrower and investor profiles, documents, and the risk acknowledgement
   - the admin verification queue and review page (rows leave the queue with the 200 ms height-and-fade animation)
5. **Add a design preview page** at `/dev/design` (local environment only, never registered in production or tests). It renders every built component in every state, in both modes, with a mode toggle. It's used to check the work and for report screenshots.

Not in this item: charts, CampaignCard, WalletCard, RiskExplanation and the other campaign, wallet and portfolio components. Build each one from this spec in the backlog item that first needs it: PB-16 and PB-17 for campaign cards and pages, PB-18 for the wallet, PB-26 for the risk explanation and charts, PB-37 for the portfolio charts.

## 4. Rules from now on (add these to CLAUDE.md)

- The design system in `docs/design-system/` is the source of truth for UI. Read its `README.md` before building any page or component.
- Pages use components from `resources/js/components` and tokens from the theme only: no raw hex colours, no arbitrary pixel values, no default shadcn/ui styling, and no emoji.
- Every animation uses the motion tokens and respects reduced motion.
- New components follow the names and props in `component-api.d.ts`.
- In Shariah mode, interface text never says "interest" or "guaranteed".

## 5. Done means

- All existing feature tests still pass, and CI is green.
- The new PATCH route for the Shariah preference has feature tests: owner only, investors only, and validated.
- Every restyled page has been checked at 1440, 820 and 390 px wide, in both modes. Headless Chrome screenshots are saved to `docs/report-notes/screenshots/design-system/`.
- `docs/report-notes/design-system.md` covers:
  - what was built and the key decisions (Tailwind v4 tokens, platform-wide mode, `@property` cross-fade, self-hosted fonts, Radix primitives)
  - a sample code excerpt, such as the mode wiring
  - a short accessibility note: contrast, focus, reduced motion
  
  This feeds Chapter 4's interface design section.
- A row in `docs/decisions.md` for adopting the design system and its packages.
