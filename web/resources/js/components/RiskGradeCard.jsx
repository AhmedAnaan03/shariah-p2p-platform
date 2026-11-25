import { formatPercent } from '../lib/format';
import Icon from './Icon';
import RiskGrade, { GRADE_MEANINGS } from './RiskGrade';
import Spinner from './Spinner';

/**
 * A campaign's AI-assisted risk grade (component-api.d.ts, RiskGradeCard): the 64 px grade
 * chip, what it means, the estimated chance of missed repayments and the model version.
 *
 * - `ready` (with `grade`): the grade.
 * - `scoring`: "Scoring…" with a spinner.
 * - `pending` (the default without a grade): the space is kept and labelled, with `sentence`
 *   saying why there is no grade yet. PB-17 reserves it; PB-25 and PB-26 fill it.
 *
 * `children` go under it: the explanation (RiskExplanation, PB-26) or its placeholder.
 */
export default function RiskGradeCard({ grade, probability, sentence, model, state, title = 'Not yet scored', children }) {
    const shown = state ?? (grade ? 'ready' : 'pending');

    return (
        <div className="space-y-5">
            <div className="flex items-center gap-4">
                {shown === 'ready' && <RiskGrade grade={grade} size="lg" />}
                {shown === 'scoring' && (
                    <span className="inline-grid size-16 shrink-0 place-items-center rounded-full border border-border bg-surface-subtle text-icon">
                        <Spinner size={24} label="Scoring" />
                    </span>
                )}
                {shown === 'pending' && (
                    <span aria-hidden="true" className="inline-grid size-16 shrink-0 place-items-center rounded-full border border-dashed border-border-control bg-surface-subtle text-icon">
                        <Icon name="hourglass" size={24} />
                    </span>
                )}
                <div className="min-w-0">
                    <p className="text-h4 text-text">
                        {shown === 'ready' ? `Grade ${grade}: ${GRADE_MEANINGS[grade]}` : shown === 'scoring' ? 'Scoring…' : title}
                    </p>
                    {shown === 'ready' && probability !== undefined && probability !== null && (
                        <p className="mt-1 text-body text-text-secondary">
                            Estimated chance of missed repayments: <span className="font-mono">{formatPercent(probability)}</span>
                        </p>
                    )}
                    {sentence && <p className="mt-1 max-w-prose text-body text-text-muted">{sentence}</p>}
                    {shown === 'ready' && model && <p className="mt-1 font-mono text-code text-text-muted">Model {model}</p>}
                </div>
            </div>
            {children}
        </div>
    );
}
