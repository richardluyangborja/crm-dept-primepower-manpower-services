export function ConfirmDialog({
  open,
  title,
  body,
  onCancel,
  onConfirm,
}: {
  open: boolean;
  title: string;
  body: string;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">{title}</h2>
        <p className="mt-2 text-sm text-[var(--text-muted)]">{body}</p>
        <div className="mt-4 flex justify-end gap-2">
          <button className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm" onClick={onCancel}>
            Cancel
          </button>
          <button className="rounded-lg bg-red-600 px-4 py-2 text-sm text-white" onClick={onConfirm}>
            Confirm
          </button>
        </div>
      </div>
    </div>
  );
}
