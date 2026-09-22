import { useEffect, useState } from 'react';
import { AlertTriangle, Info } from 'lucide-react';

export function ConfirmDialog({
  open,
  title,
  body,
  tone = 'danger',
  confirmLabel = 'Confirm',
  details,
  input,
  onCancel,
  onConfirm,
}: {
  open: boolean;
  title: string;
  body: string;
  tone?: 'danger' | 'info';
  confirmLabel?: string;
  details?: string[];
  input?: { label: string; placeholder?: string; required?: boolean; initial?: string };
  onCancel: () => void;
  onConfirm: (value?: string) => void;
}) {
  const [value, setValue] = useState(input?.initial ?? '');
  const [err, setErr] = useState('');
  useEffect(() => {
    if (open) {
      setValue(input?.initial ?? '');
      setErr('');
    }
  }, [open]); // eslint-disable-line react-hooks/exhaustive-deps
  if (!open) return null;
  const danger = tone === 'danger';
  const confirm = () => {
    if (input?.required && !value.trim()) {
      setErr('This field is required.');
      return;
    }
    onConfirm(input ? value.trim() : undefined);
  };
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card w-full max-w-md p-6">
        <h2 className="flex items-center gap-2 text-lg font-semibold">
          {danger ? <AlertTriangle size={20} className="shrink-0 text-red-600" /> : <Info size={20} className="shrink-0 text-sky-600" />}
          {title}
        </h2>
        <p className="mt-2 text-sm text-[var(--text-muted)]">{body}</p>
        {details && details.length > 0 && (
          <ul className="mt-2 list-disc rounded-lg border border-[var(--border)] p-3 pl-8 text-sm">
            {details.map((d, i) => (
              <li key={i}>{d}</li>
            ))}
          </ul>
        )}
        {input && (
          <label className="mt-3 block text-xs font-medium">
            {input.label}{input.required ? ' *' : ''}
            <input
              autoFocus
              value={value}
              onChange={(e) => setValue(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && confirm()}
              placeholder={input.placeholder}
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm font-normal"
            />
          </label>
        )}
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm" onClick={onCancel}>
            Cancel
          </button>
          <button
            className={`rounded-lg px-4 py-2 text-sm text-white ${danger ? 'bg-red-600' : 'bg-sky-600'}`}
            onClick={confirm}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
