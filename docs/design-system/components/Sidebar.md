# Sidebar

The portal navigation: a light, patterned panel with the wordmark, nav groups, the active page, and a collapse control.

**Provide** `portal` (`"Investor"` | `"Borrower"` | `"Admin"`), `groups` (`{label?, items: [{icon, label, active?, count?}]}`), `collapsed`, `footer` (shown above Collapse, for example the mode switch in the mobile drawer), `onNavigate`.

- A light panel: `color-band` (white with a whisper of the mode colour) behind the mode's `BackgroundPattern`, 1 px `color-border` on the right. 264 px wide, 72 px collapsed (labels hide, counts move to the corner, titles become tooltips).
- Items 40 px, `radius-md`, `color-text-secondary` with `color-icon` icons; hover `color-band-hover`; active: a white `color-nav-active` tile with `color-border`, `shadow-xs`, `color-nav-active-ink` text, a Bold icon in `color-primary`, and a 3 px `color-primary` indicator on the left edge.
- No logo exists yet: "Amanah" is set in Bricolage 700 with a full stop in the mode's primary colour.
- When the app shell is narrower than 1024 px it becomes the `MobileNav` drawer. It never animates on page change.
