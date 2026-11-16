/**
 * A bulleted list. Each item is a string, or { title, text } for a bold lead-in followed by an
 * explanation (as on the risk acknowledgement). Running text holds 60–75 characters a line.
 */
export default function BulletList({ items }) {
    return (
        <ul className="max-w-prose list-disc space-y-2 pl-5 text-body text-text-secondary marker:text-icon">
            {items.map((item) =>
                typeof item === 'string' ? (
                    <li key={item}>{item}</li>
                ) : (
                    <li key={item.title}>
                        <strong className="font-semibold text-text">{item.title}</strong> {item.text}
                    </li>
                ),
            )}
        </ul>
    );
}
