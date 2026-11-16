import { usePage } from '@inertiajs/react';
import Alert from './Alert';

/**
 * Shows the one-off message the server flashed with ->with('status', '...'), inline. Used on the
 * auth pages; portal pages show the same message as a toast (PortalLayout).
 */
export default function StatusMessage() {
    const { flash } = usePage().props;

    if (!flash?.status) {
        return null;
    }

    return <Alert tone="success">{flash.status}</Alert>;
}
