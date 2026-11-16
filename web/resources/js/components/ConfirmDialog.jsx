import { useEffect, useState } from 'react';
import Button from './Button';
import Checkbox from './Checkbox';
import DescriptionList from './DescriptionList';
import Dialog from './Dialog';
import Money from './Money';

/**
 * Asks the user to confirm something that matters before it happens: every Danger button and
 * every pledge (component-api.d.ts, ConfirmDialog). It repeats what will happen in plain words.
 *
 *   <ConfirmDialog open={open} onClose={...} onConfirm={...} tone="danger"
 *       title="Reject this account?" consequence="Aishath will see your reason and can upload new documents."
 *       confirmLabel="Reject and notify user" loading={form.processing} />
 *
 * `amount` shows a large figure, `rows` a short list of [label, value] details, `simulated`
 * the "Simulated wallet, no real money." line, and `ack` a checkbox that must be ticked first.
 */
export default function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    amount,
    rows,
    consequence,
    confirmLabel = 'Confirm',
    tone = 'primary',
    loading = false,
    simulated = false,
    ack,
}) {
    const [acknowledged, setAcknowledged] = useState(false);

    // Every time it opens, the box starts unticked.
    useEffect(() => {
        if (open) setAcknowledged(false);
    }, [open]);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            title={title}
            size="sm"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose} disabled={loading}>
                        Cancel
                    </Button>
                    <Button variant={tone === 'danger' ? 'danger' : 'primary'} onClick={onConfirm} loading={loading} disabled={Boolean(ack) && !acknowledged}>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                {amount !== undefined && <Money value={amount} size="figure-xl" />}
                {rows && <DescriptionList items={rows} columns={1} />}
                {consequence && <p className="text-body text-text-secondary">{consequence}</p>}
                {simulated && <p className="text-body-sm text-text-muted">Simulated wallet, no real money.</p>}
                {ack && <Checkbox id="confirm-ack" label={ack} checked={acknowledged} onChange={setAcknowledged} />}
            </div>
        </Dialog>
    );
}
