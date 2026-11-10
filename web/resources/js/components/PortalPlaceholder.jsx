/**
 * Body of a placeholder portal page: a heading and a list of what later backlog items add.
 */
export default function PortalPlaceholder({ heading, comingSoon }) {
    return (
        <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h1 className="text-2xl font-semibold">{heading}</h1>
            <p className="mt-2 text-sm text-slate-600">This portal is a placeholder. Coming in later sprints:</p>
            <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                {comingSoon.map((item) => (
                    <li key={item}>{item}</li>
                ))}
            </ul>
        </section>
    );
}
