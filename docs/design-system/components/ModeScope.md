# ModeScope

Puts everything inside it in conventional or Shariah mode. The mode is platform-wide: when an investor turns on Shariah-only, the whole app (backgrounds, text, borders, sidebar, charts and the background pattern) switches to the Shariah palette from the first screen.

**Provide** `mode` (`"conventional"` | `"shariah"`), optional `appearance` (`"light"` | `"dark"`) to force a theme, `as` for the element, and `children`. Put it once at the root of the app; `usePlatformMode()` keeps the value.

**How it works.** Each theme sets every colour token: conventional is white and slate greys with navy accents; Shariah is white and moss greys with muted green accents, so greens never sit on navy. Every colour token is registered as an `@property`, so a switch cross-fades the whole palette over `duration-mode` (500 ms), and `BackgroundPattern` cross-fades from waves to the khatam at the same time. With reduced motion the switch is instant.

**In code** the switch is `data-mode="conventional|shariah"` next to `data-theme="light|dark"` on `<html>` (see `tailwind-theme.css`). Write the two attributes separately.

- Do show Shariah status of a single campaign with the `ShariahBadge`, whatever the platform mode.
- Don't nest a second mode inside a page to tint one card: the palette always follows the platform.
