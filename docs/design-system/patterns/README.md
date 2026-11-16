# Patterns

The background texture for bands, the sidebar, the mode panel and empty states. One pattern per mode; switching mode cross-fades between them.

- `wave-tile.svg`: conventional mode. A 48 × 24 px tile of two offset ripple lines (lagoon water, not geometry), ink `navy-600`. Repeats seamlessly.
- `khatam-tile.svg`: Shariah mode. A 48 × 48 px tile of the eight-pointed khatam lattice, ink `green-700`. Repeats seamlessly.
- Use either as `background: url(tile) 0 0 / auto repeat` on a layer at `opacity-pattern` (0.07; `opacity-pattern-dark` 0.09 in dark). The ink is full strength in the file; the layer's opacity makes it quiet.
- `khatam-icon-16.svg`: the 16 px khatam for the Shariah badge and the mode switch. It draws in `currentColor` so it follows the text colour it sits in.
- In React build `BackgroundPattern` (both layers, cross-faded by mode), `WaveTexture`, `MotifTexture` and `KhatamIcon`, drawing in `currentColor` (`color-pattern`) so they follow the mode.
- The khatam never appears with calligraphy or religious text, and neither pattern goes behind tables or charts.
