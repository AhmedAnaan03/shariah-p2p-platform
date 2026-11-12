/**
 * The large heading at the top of a public page: a small coloured line above the title
 * (`eyebrow`), the title, and an optional lead paragraph.
 */
export default function PageHeading({ eyebrow, title, lead }) {
    return (
        <header>
            {eyebrow && <p className="text-sm font-medium uppercase tracking-wide text-emerald-700">{eyebrow}</p>}
            <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">{title}</h1>
            {lead && <p className="mt-4 max-w-2xl text-slate-600">{lead}</p>}
        </header>
    );
}
