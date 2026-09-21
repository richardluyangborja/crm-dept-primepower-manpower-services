import { TrendingDown, TrendingUp } from 'lucide-react';

export function KpiCard({
  label,
  value,
  sub,
  delta,
}: {
  label: string;
  value: string;
  sub?: string;
  delta?: { text: string; good: boolean };
}) {
  return (
    <div className="card p-4">
      <p className="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">{label}</p>
      <p className="mt-1 text-2xl font-bold tabular-nums">{value}</p>
      <p className="mt-1 text-xs text-[var(--text-muted)]">
        {sub}{' '}
        {delta && (
          <span className={`inline-flex items-center gap-0.5 ${delta.good ? 'text-green-600' : 'text-red-600'}`}>
            {delta.good ? <TrendingUp size={12} /> : <TrendingDown size={12} />} {delta.text}
          </span>
        )}
      </p>
    </div>
  );
}
