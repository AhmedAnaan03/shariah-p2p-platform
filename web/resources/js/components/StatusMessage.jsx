import { usePage } from '@inertiajs/react';

/**
 * Shows the one-off message the server flashed with ->with('status', '...'), if any.
 */
export default function StatusMessage() {
    const { flash } = usePage().props;

    if (!flash?.status) {
        return null;
    }

    return (
        <div
            role="status"
            className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {flash.status}
        </div>
    );
}
