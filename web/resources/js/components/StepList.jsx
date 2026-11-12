import Badge from './Badge';
import Button from './Button';

/**
 * A numbered checklist, e.g. the verification steps on a portal home page.
 * `steps` is a list of { title, description, done, href, actionLabel }; a step that isn't done
 * and has an href shows a button to go and do it.
 */
export default function StepList({ steps }) {
    return (
        <ol className="space-y-3">
            {steps.map((step, index) => (
                <li
                    key={step.title}
                    className={`flex flex-wrap items-start justify-between gap-3 rounded-md border p-4 ${
                        step.done ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'
                    }`}
                >
                    <div className="flex gap-3">
                        <span
                            aria-hidden="true"
                            className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${
                                step.done ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-700'
                            }`}
                        >
                            {step.done ? '✓' : index + 1}
                        </span>
                        <div>
                            <p className="font-medium">
                                {step.title} {step.done && <Badge variant="success">Done</Badge>}
                            </p>
                            {step.description && <p className="mt-0.5 text-sm text-slate-600">{step.description}</p>}
                        </div>
                    </div>
                    {!step.done && step.href && (
                        <Button href={step.href} size="sm" variant="secondary">
                            {step.actionLabel}
                        </Button>
                    )}
                </li>
            ))}
        </ol>
    );
}
