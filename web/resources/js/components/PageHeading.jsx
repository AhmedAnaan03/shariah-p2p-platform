/**
 * The heading at the top of a public page (the welcome page): an overline eyebrow, the h1 and
 * an optional lead paragraph. Not a hero: the largest header is a page band with 32 px padding.
 */
export default function PageHeading({ eyebrow, title, lead }) {
    return (
        <header>
            {eyebrow && <p className="text-overline text-primary-ink">{eyebrow}</p>}
            <h1 className="mt-3 text-h1 text-text">{title}</h1>
            {lead && <p className="mt-4 max-w-prose text-body-lg text-text-secondary">{lead}</p>}
        </header>
    );
}
