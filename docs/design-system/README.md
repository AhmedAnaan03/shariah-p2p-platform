Amanah P2P is a peer-to-peer financing and SME crowdinvesting platform for the Maldives, with AI-assisted risk grades and an optional Shariah-compliant mode. Borrowers raise financing, investors pledge from a simulated wallet, administrators verify people and documents. Many users are new to P2P finance, so the interface aims to feel like a calm, careful private bank: trust and clarity before excitement. It is a browser web app used on desktops, tablets and phones alike. ("Amanah" is a placeholder name; there is no logo yet, so the name is set in Bricolage Grotesque 700 as a wordmark, with a full stop in the mode's primary colour.)

## Principles

- **Light, calm, precise.** White and near-white surfaces carry the interface; colour is a quiet tint plus one primary accent. Thin borders and exact figures. Not a crypto app, not a startup landing page.
- **One mode at a time.** The platform is either conventional (slate and navy) or Shariah (moss and muted green). The two palettes never share a screen.
- **Numbers are the interface.** Money, percentages and dates are set in IBM Plex Mono with tabular figures, right-aligned in tables, the currency code smaller and muted before the figure: `MVR 12,500.00`.
- **Explain, don't decorate.** Every visual answers a question the user has: "how funded is it?", "why grade B?", "when do I get paid?".
- **Quiet motion.** Animation confirms an action or shows a change. It never performs.
- **Shariah identity is respectful and consistent.** The muted green palette and the khatam pattern belong to Shariah mode; a single campaign's Shariah status is always the badge with the word "Shariah".

## Voice and content

- Plain, friendly, specific. "You could lose some or all of your money", not legal jargon. "We're checking your documents. This usually takes two working days."
- Sentence case everywhere, including buttons and titles. No exclamation marks, no emoji.
- Buttons name the action and the amount: "Pledge MVR 5,000", "Approve borrower", "Upload document", "Confirm pledge of MVR 5,000.00".
- Errors say what went wrong and how to fix it: "Enter the number in the format C-0412/2021." "This file is 7.2 MB. Files must be 5 MB or smaller."
- Explain Islamic finance terms on first use with a `Term` tooltip. Murabahah: "a sale at cost plus an agreed, fixed markup". Mudarabah: "profit sharing; returns depend on the business's actual profit".
- In Shariah mode, text must never present returns as guaranteed or as interest. Never write "interest" or "guaranteed"; say "markup" (Murabahah) or "profit share" (Mudarabah), and "expected returns". Risk warnings still say the same thing in other words, just as clearly: "Your returns could be lower than expected, or you could receive nothing", not "Returns are not guaranteed". Naming something that is not a return, such as a "deposit guarantee scheme", is fine.
- Formats: dates `12 Nov 2026`, chart months `Nov 26`, times `14:30`, money `MVR 12,500.00` (compact `MVR 12.5K` on chart axes only), percentages to one decimal `12.4%`, phone `+960 774 2210`, national ID `A123456`.
- The wallet card and every pledge confirmation say "Simulated wallet, no real money." (academic prototype).

## Modes: conventional and Shariah

The mode is platform-wide. When an investor turns on **Shariah-only** (the `ModeSwitch` in the top bar, the switch in the dashboard's mode panel, or the mobile menu), the whole platform switches to the Shariah palette from the first screen: page background, surfaces, text, borders, sidebar, buttons, charts and the background pattern. A borrower who chooses a Murabahah or Mudarabah contract, and the admin Shariah-rules settings, switch the same way.

- Wrap the app in `ModeScope mode={mode}` (in app CSS: `data-mode="conventional|shariah"` next to `data-theme="light|dark"`). Don't nest a second mode to tint one card.
- The switch cross-fades every colour token and the background pattern over `duration-mode` (500 ms) with `ease-mode`, an even ease-in-out so the change reads as one smooth dissolve. With reduced motion it is instant.
- In conventional mode, Shariah campaigns are still listed; each carries the `ShariahBadge` (khatam plus the word "Shariah") and its contract badge. Shariah status is never shown by colour alone, so it reads for colour-blind users and in greyscale prints.
- In Shariah mode never present returns as guaranteed or as interest (see "Voice and content" for the wording, including risk warnings).

## Colour

Four themes: `conventional`, `shariah`, `dark` (conventional) and `shariah-dark`. Light is the primary experience; dark is secondary. The UI uses semantic tokens only; the scales exist to define them and for sequential charts.

- **Scales.** Conventional: `slate-*` neutrals (white with a cool navy tint) and the `navy-*` accent scale. Shariah: `moss-*` neutrals (white with a soft green tint) and the muted `green-*` scale (Islamic Green and Sage with about a third less chroma). `brand-deep-navy`, `brand-teal-green`, `brand-islamic-green` and `brand-sage` keep the original anchors exact for the wordmark, print and marketing.
- **Surfaces are white.** Cards, inputs, tables, the top bar and modals are `color-surface` / `color-surface-raised` (white). The page is `color-bg`, a near-white tinted toward the mode. The sidebar, page bands and hero strips use `color-band`, one step more tinted, behind the background pattern. `color-surface-subtle` fills table headers and hover rows. Cards rely on a 1 px `color-border` more than shadow.
- **Text.** `color-text` (slate-900 / moss-900) for primary text, `color-text-secondary` for values, `color-text-muted` for helper text and labels; all 4.5:1+ on every surface in every theme. `color-text-placeholder` is 3.6:1: placeholders are examples, never instructions.
- **Accent.** `color-primary` (navy-600 / green-600) fills primary buttons, checked controls, progress, active indicators and received money; text on it is `color-primary-fg`. Tinted areas use `color-primary-subtle` or `color-primary-muted` with `color-primary-ink` text. Links use `color-link`. `color-accent-soft` is for fills and highlights, never text.
- **Sidebar.** `color-band` with the pattern and a right border; items in `color-text-secondary`; the active item is a white `color-nav-active` tile with a border, `shadow-xs`, `color-nav-active-ink` text and a 3 px `color-primary` indicator.
- **Controls.** Inputs, checkboxes, radios and switches use `color-border-control` (3:1+) so the edge is visible.
- **Status** colours are shared by both modes and kept light: olive success (`color-success`, never the Shariah green), amber warning, coral-red danger, sky-blue info, slate neutral, each with `-bg` and `-border`, always with an icon and a word. Pending verification → warning; verified, paid, funded → success; rejected, failed, late, defaulted → danger; live → info; draft → neutral.
- **Risk grades** are shared by both modes and never green: sky (A), teal (B), ochre (C), orange (D), rose (E). Chips are tinted by default (`risk-x-ink` letter on `risk-x-soft`); solid chips fill with `risk-x-ink`; charts and the 64 px chip use the lighter `risk-x`. Always show the letter.
- **Charts.** Categorical series `chart-1` … `chart-6` (the first two follow the mode; ochre, lavender, coral and grey are shared). Received `chart-received`, expected `chart-expected` with a dashed outline, late `chart-late`. In the risk explanation `chart-raise` (coral) raises risk and `chart-lower` (the primary) lowers it. Gridlines `chart-grid`, ticks `chart-axis`.
- **Dark** themes are near-black tinted to the mode (slate or moss), with lighter primaries (navy-400 / green-400) and dark text on them.

## Background pattern

`BackgroundPattern` sits behind `color-band` areas, the mode panel and banded empty states, in `color-pattern` at `opacity-pattern` (7%; 9% in dark), fading out toward the content.

- **Conventional: lagoon ripples.** Two offset rows of soft sine lines on a 48 px tile: water, not geometry, so it never reads as Islamic pattern.
- **Shariah: the khatam.** The eight-pointed star lattice on a 48 px tile.
- Both layers are always rendered; a mode switch cross-fades them (with a 4% scale and a 6° turn) in step with the colours.
- Never behind tables or charts, never above 9%, never with calligraphy or religious text. Tiles are in the **Patterns** asset group.

## Layout across devices

Every screen is one responsive layout, not a separate mobile build. The app shell is a container: it responds to its own width, so the same code works in a phone browser, a tablet, a desktop window or a split view.

- **1024 px and up:** sidebar 264 px (collapsible to 72), top bar with search, 12-column grid with 24 px gutters, 32 px page padding, content max 1200 px.
- **640–1023 px:** the sidebar becomes the `MobileNav` drawer behind a menu button; search leaves the top bar; most blocks span the full width, stat cards sit three across and paired cards two across; the campaign pledge panel and the borrower's next instalment move to the top; 20–24 px padding.
- **Below 640 px:** one column, 16 px padding and gaps, band actions stretch full width, the mode switch keeps its icon and switch, steppers turn vertical, description lists use two columns, tables become stacked cards (any table narrower than 720 px stacks).
- Touch targets are at least `touch-target` 44 px on coarse pointers. Example screens are shown at 1440, 820 and 390 px.

## Lists, search and return navigation

- Every list (a table or list of records) uses the shared `ListFilters` bar above it: a search box (search as you type, 300 ms after the last key), filters suited to that list (status, role, contract type, purpose, ...), and "Clear filters" once anything is set. Filtering happens on the server; the values stay in the URL so a filtered list can be bookmarked or returned to. The number of results is announced politely to screen readers.
- An empty list says why: "No campaigns match these filters" (with the search illustration) is different from "No campaigns are open right now".
- Links from one page to another pass the page they came from (`return_to`). Back links and breadcrumbs on the next page say where they go ("Back to the campaign review") and return there with the filters and tab intact. Only internal paths are accepted.

## Typography

- **Bricolage Grotesque** (500–700, optical sizing on) for `display`, `h1`, `h2`, `h3`, and the risk-grade letter. **Figtree** (400–600) for interface and body text: `h4`, `body-lg`, `body`, `body-sm`, `label`, `overline`. **IBM Plex Mono** (400, 500) with `tabular-nums` for `figure-xl`, `figure-lg`, `figure` and `code`. The font files are in `fonts/`.
- `display` is for marketing pages only. One `h1` per page; `h2` for sections; `h3` for card titles; `h4` for modal titles and sub-sections. Headings use `text-wrap: balance`.
- `overline` is always uppercase: section eyebrows, table headers, stat-card labels.
- Running text holds 60–75 characters a line.

## Spacing and layout

- 4 px base: `space-0` … `space-24`. Label to input `space-2`; between fields `space-5`; between form sections `space-8`; between cards `space-6`; between page sections `space-12`.
- Buttons sm 6 × 12, md 10 × 16, lg 12 × 20; inputs `control-sm` 32, `control-md` 40, `control-lg` 48 px tall with 12 px horizontal padding; cards 24 px padding (20 on mobile); modals 24, footer 16 × 24; table cells 12 × 16 with `row` 52 px (`row-compact` 40).
- App shell: `sidebar` 264 px (`sidebar-collapsed` 72) on `color-band`; `topbar` 64 px, white, bottom border; content max `content-max` 1200 px with 32 px gutters. 12-column grid, 24 px gutters; dashboards use 4, 5, 7, 8 and 12-column spans. See "Layout across devices" for the smaller widths.
- Breakpoints `bp-sm` 640, `bp-md` 768, `bp-lg` 1024, `bp-xl` 1280, `bp-2xl` 1536.

## Shape, borders and elevation

- Radii are restrained: `radius-xs` 4 (checkboxes, tooltips, table tags), `radius-sm` 6 (badges, chips, progress), `radius-md` 8 (buttons, inputs, menu items), `radius-lg` 12 (cards, panels, dropzones, toasts), `radius-xl` 16 (modals, drawers, page bands), `radius-full` (avatars, toggles, grade circles). Not everything is a pill.
- Borders are 1 px `color-border`; focused inputs get a `color-focus-ring` border plus a 3 px `color-primary-muted` halo; the dropzone and "expected" chart values are dashed 1.5 px.
- Shadows are tinted to the mode's ink, never black, and soft: `shadow-xs` resting cards, inputs and the active nav item, `shadow-sm` hovered link cards and sticky headers, `shadow-md` dropdowns, popovers, toasts, chart tooltips, `shadow-lg` modals and drawers.
- Keyboard focus: a solid 2 px `color-focus-ring` outline with a 2 px offset, on `:focus-visible` only.
- Overlay: `color-overlay` (the mode's text colour at 40%) with a 2 px backdrop blur. Layers: `z-sticky` 10, `z-dropdown` 20, `z-drawer` 30, `z-modal` 40, `z-toast` 50, `z-tooltip` 60. Disabled: `opacity-disabled`.

## Iconography and imagery

- **Phosphor Icons**, Regular weight, 20 px (16 in dense tables and badges, 24 in empty states and file rows), in `currentColor` (`color-icon` at rest). Bold only for the active sidebar item. In the app use `@phosphor-icons/react`.
- **No emoji** anywhere in the interface.
- **Khatam**: a 16 px icon inside the Shariah badge and the mode switch; as a texture, only through `BackgroundPattern` in Shariah mode (see above).
- **No stock photography of people.** Empty states use simple line illustrations in `color-border-control` and `color-accent-soft` on `color-primary-subtle`, optionally on a patterned `color-band`. People are shown as initials in an `Avatar`.

## Motion

- Durations: `duration-instant` 100 ms (press, tick), `duration-fast` 150 ms (hover, focus, exits), `duration-base` 200 ms (dropdowns, popovers, toasts), `duration-moderate` 250 ms (page, drawer, modal), `duration-slow` 400 ms (progress, bars), `duration-mode` 500 ms (the conventional ⇄ Shariah cross-fade), `duration-count` 800 ms (count-up).
- Easing: `ease-standard` for most transitions, `ease-enter` for arrivals, `ease-exit` for departures, `ease-mode` (ease-in-out) for the mode cross-fade. No bounce; if a spring is needed, stiffness 400, damping 40.
- Mode switch: every colour token is registered with `@property`, so changing `data-mode` / `data-theme` dissolves the whole palette, and the background pattern's two layers cross-fade with a slight scale and turn, all in 500 ms.
- Page change: content fades in and rises 8 px over 250 ms; the old page fades out over 150 ms; the sidebar and top bar never animate.
- Buttons press to 0.98. Link cards rise 2 px to `shadow-sm` on hover; other cards never move. Dropdowns scale from 0.97; modals from 0.96 with the overlay fading in; drawers slide from their edge; toasts slide 16 px from the right and leave after 5 s (paused on hover). Error messages slide down 4 px.
- Progress bars and rings fill from 0 on first view and animate between values when a pledge lands. Wallet balances and totals count up from 0 on first view and tween on update; never animate a number the user is typing. Chart bars grow from the baseline and lines draw in, staggered 40 ms per series, on first view only. Skeletons appear after 300 ms and shimmer every 1.4 s. A removed row (an approved user leaving the queue) animates its height and opacity over 200 ms.
- With `prefers-reduced-motion: reduce`, remove movement and scaling, keep fades of 100 ms or less, and show numbers and progress at their final values.
- In the app, use Motion (Framer Motion) with these values, for example `transition={{ duration: 0.25, ease: [0, 0, 0.2, 1] }}`, and `useReducedMotion()`.

## Data visualisation

- Every chart sits in a `ChartFrame`: a visible title, a one-line takeaway above it, a legend top-left with 8 px dots, and "View as table" for an accessible data table.
- Horizontal gridlines only (`chart-grid`), no axis lines, ticks in `body-sm` `chart-axis`. Bars have 4 px top corners; lines are 2 px with points only on hover; donuts are 14 px thick with 2 px gaps and the total in the centre.
- Tooltip: `color-surface-raised`, `radius-md`, `shadow-md`, overline title, figures in Plex Mono, a key dot per series.
- Never rely on colour alone: label bars directly, dash expected values, and repeat direction with arrows and signs (the risk explanation does all three).
- The bundle draws charts in SVG for these previews; each chart card lists the Recharts equivalent to build with.

## Accessibility

- WCAG 2.2 AA: text at least 4.5:1, large text, icons, control outlines and focus rings at least 3:1, checked in all four themes.
- Visible keyboard focus everywhere and a logical tab order. Build dialogs, menus, tabs, popovers, selects, tooltips, switches and toasts on Radix primitives.
- Status, Shariah mode and risk grades are never conveyed by colour alone: badges carry icons and words, the Shariah badge carries the motif and the word, grades carry their letter.
- Respect `prefers-reduced-motion` as described under Motion.

## Do and don't: avoiding the generic look

- Do give every card a job and a clear figure. Don't repeat identical grey cards with a rounded-2xl-plus-shadow look on every element.
- Do use borders first and the four navy-tinted shadows sparingly. Don't use glassmorphism, purple or blue gradients, or gratuitous gradients of any kind.
- Do let money and numbers carry the page in Plex Mono. Don't build oversized hero sections; the largest header is a page band with 32 px padding.
- Do use Phosphor icons. Don't use emoji as icons.
- Do style components from these tokens. Don't ship default shadcn/ui styling (its neutral greys, 6 px radius everywhere and generic focus rings).
- Do keep the whole screen in one mode's palette. Don't put green blocks on navy (or navy on green), and don't use the Shariah green for success messages or risk grades.
- Do keep large areas white or near-white. Don't paint sidebars, headers or dashboards in dark brand colour in the light themes.

## Using the system in code

- Tokens for the app: `assets/Code/amanah-tokens.css` (CSS custom properties, `[data-mode="shariah"]` and `[data-theme="dark"]` overrides, and the `@property` registrations that make the cross-fade work) or `assets/Code/tailwind-theme.css` (Tailwind CSS v4 `@theme`). Put `data-mode` and `data-theme` on `<html>`.
- Each component card names the Radix primitive, Motion setting or Recharts configuration to build it with; `components/index.d.ts` documents every prop.
