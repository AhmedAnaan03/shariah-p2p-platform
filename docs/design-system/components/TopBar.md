# TopBar

The white bar above every page: title, global search, the Shariah-mode indicator, notifications and the user menu.

**Provide** `title`, optional `breadcrumb`, `mode` and `onModeChange` (shows the `ModeSwitch`: the platform-wide Shariah-only toggle, with a khatam, a label that hides on phones, and a switch), `unread` (bell count; 9+ above nine), `user` (`{name, role}`), `search` (default on; hidden below 1024 px), `onMenu` (the menu button, shown below 1024 px).

- 64 px tall, `color-surface`, 1 px bottom `color-border`, sticky at `z-sticky`. Page gutter 32 px (16 on mobile).
- The bell's count is `color-danger` with a 2 px surface ring and an `aria-label` that says how many are unread.
- Search uses the small input on `color-surface-subtle` with a `/` shortcut hint.
