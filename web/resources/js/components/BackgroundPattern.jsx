import khatamTile from '../../images/patterns/khatam-tile.svg';
import waveTile from '../../images/patterns/wave-tile.svg';

/*
 * The background texture (README, "Background pattern"): lagoon ripples in conventional mode,
 * the khatam lattice in Shariah mode, in `color-pattern` at 7% (9% in dark).
 *
 * The SVG tile is used as a CSS mask over a block filled with currentColor, so the ink is the
 * `color-pattern` token, not the colour baked into the file, and it cross-fades with the rest
 * of the palette. Only behind bands, the sidebar, the mode panel and banded empty states:
 * never behind tables or charts.
 */

function Texture({ tile, width, height, className = '' }) {
    return (
        <div
            aria-hidden="true"
            className={`absolute -inset-24 bg-current ${className}`}
            style={{
                maskImage: `url(${tile})`,
                WebkitMaskImage: `url(${tile})`,
                maskSize: `${width}px ${height}px`,
                WebkitMaskSize: `${width}px ${height}px`,
                maskRepeat: 'repeat',
                WebkitMaskRepeat: 'repeat',
            }}
        />
    );
}

/** The conventional ripple tile alone (48 × 24 px). */
export function WaveTexture({ size = 48, className = '' }) {
    return <Texture tile={waveTile} width={size} height={size / 2} className={className} />;
}

/** The Shariah khatam tile alone (48 × 48 px). */
export function MotifTexture({ size = 48, className = '' }) {
    return <Texture tile={khatamTile} width={size} height={size} className={className} />;
}

// Where the pattern fades out toward the content.
const FADES = {
    down: 'linear-gradient(to bottom, black 0%, transparent 70%)',
    corner: 'radial-gradient(ellipse at top right, black 0%, transparent 75%)',
};

/**
 * Both textures, stacked; the CSS in app.css (.pattern-wave / .pattern-khatam) shows the one
 * for the current mode. Place it first inside a `relative` element.
 *
 *   <section className="relative ..."><BackgroundPattern fade="down" /> ...</section>
 */
export default function BackgroundPattern({ fade = true, className = '' }) {
    const mask = fade === 'down' ? FADES.down : fade ? FADES.corner : undefined;

    return (
        <div
            aria-hidden="true"
            className={`pointer-events-none absolute inset-0 overflow-hidden text-pattern ${className}`}
            style={mask ? { maskImage: mask, WebkitMaskImage: mask } : undefined}
        >
            <WaveTexture className="pattern-layer pattern-wave" />
            <MotifTexture className="pattern-layer pattern-khatam" />
        </div>
    );
}
