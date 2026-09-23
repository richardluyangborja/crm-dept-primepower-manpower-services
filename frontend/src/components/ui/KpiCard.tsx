import { TrendingDown, TrendingUp } from 'lucide-react';
import { Link } from 'react-router-dom';

export function KpiCard({
  label,
  value,
  sub,
  delta,
  href,
}: {
  label: string;
  value: string;
  sub?: string;
  delta?: { text: string; good: boolean };
  href?: string;
}) {
  const body = (
    <div className={`card p-4 ${href ? 'transition-shadow hover:shadow-md' : ''}`}>
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
  return href ? <Link to={href} className="block">{body}</Link> : body;
}
