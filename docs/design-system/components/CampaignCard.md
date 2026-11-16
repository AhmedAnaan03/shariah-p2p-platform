# CampaignCard

A campaign in a browse grid or dashboard: business, purpose, contract and risk grade, the terms, and funding progress. The whole card links to the detail page.

**Provide** `name`, `sector`, `purpose`, `contract` (`conventional` | `murabahah` | `mudarabah`), `grade`, `amount`, `raised`, `tenure` (months), `rate` (the figure only, for example "8.0%", "9.5%" or "60 : 40"; the label above it follows the contract), `investors`, `daysLeft`, `status`, `href`, or `state="loading"` for the skeleton.

- The card follows the platform mode like everything else (no per-card palette). A Murabahah or Mudarabah campaign always carries the `ShariahBadge` and its contract badge, so it is recognisable on a conventional page too.
- Hover rises 2 px and goes from `shadow-xs` to `shadow-sm` over 150 ms. Non-link cards never move.
- The rate label changes with the contract: Interest / Markup / Profit share.
