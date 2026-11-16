# Button

Starts an action. The label says exactly what happens: "Pledge MVR 5,000", "Approve borrower", "Upload document".

**Provide** `children` (sentence-case label), `variant` (`primary` | `secondary` | `ghost` | `danger` | `link`), `size` (`sm` 32 px | `md` 40 px | `lg` 48 px), optional `iconLeft` / `iconRight` / `icon` (icon-only: also pass `aria-label`), `loading`, `disabled`, and any `<button>` props.

**States** hover (`color-primary-hover`, 150 ms), pressed (`color-primary-pressed`, scale 0.98 over 100 ms), focus-visible (2 px `color-focus-ring`, 2 px offset), disabled (`opacity-disabled`), loading (a 16 px spinner replaces the icon, the label stays, width holds, `aria-busy`).

- One primary button per view region. Secondary is white with a `color-border-strong` border.
- Danger is for destructive or irreversible actions and is always followed by a `ConfirmDialog`.
- Inside Shariah mode the primary turns green with no code change.
- Build with a plain `<button>` (or Radix `Slot` for `asChild`); press scale via Motion `whileTap={{ scale: 0.98 }}` with `duration: 0.1`.
