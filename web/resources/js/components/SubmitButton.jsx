/**
 * Primary form button, disabled while the form is being sent so it can't be double-submitted.
 */
export default function SubmitButton({ processing, children }) {
    return (
        <button
            type="submit"
            disabled={processing}
            className="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:opacity-60"
        >
            {children}
        </button>
    );
}
