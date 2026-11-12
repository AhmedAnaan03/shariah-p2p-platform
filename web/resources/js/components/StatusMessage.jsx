import { usePage } from '@inertiajs/react';
import Alert from './Alert';

/**
 * Shows the one-off message the server flashed with ->with('status', '...'), if any.
 */
export default function StatusMessage() {
    const { flash } = usePage().props;

    if (!flash?.status) {
        return null;
    }

    return <Alert variant="success">{flash.status}</Alert>;
}
