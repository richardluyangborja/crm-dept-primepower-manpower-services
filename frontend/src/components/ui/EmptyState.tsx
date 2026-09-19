import type { ReactNode } from 'react';

export function EmptyState({ title, hint, action }: { title: string; hint: string; action?: ReactNode }) {
  return (
    <div className="card flex flex-col items-center gap-2 p-10 text-center">
      <p className="text-lg font-semibold">{title}</p>
      <p className="text-sm text-[var(--text-muted)]">{hint}</p>
      {action}
    </div>
  );
}
