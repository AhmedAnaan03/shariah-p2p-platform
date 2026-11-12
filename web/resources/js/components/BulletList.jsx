/**
 * A bulleted list. Each item is a string, or { title, text } for a bold lead-in followed by
 * an explanation (as on the risk acknowledgement).
 */
export default function BulletList({ items }) {
    return (
        <ul className="list-disc space-y-2 pl-5 text-sm text-slate-700">
            {items.map((item) =>
                typeof item === 'string' ? (
                    <li key={item}>{item}</li>
                ) : (
                    <li key={item.title}>
                        <strong className="font-semibold text-slate-900">{item.title}</strong> {item.text}
                    </li>
                ),
            )}
        </ul>
    );
}
