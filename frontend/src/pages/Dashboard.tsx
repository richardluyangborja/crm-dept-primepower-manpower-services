import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { KpiCard } from '../components/ui/KpiCard';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { AiBadge, FeedbackThumbs } from '../components/crm/InsightBits';

export function DashboardPage() {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await api.get('/dashboard/summary')).data.data,
  });

  if (isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading dashboard…</p>;
  if (isError)
    return (
      <div className="card p-6 text-sm">
        Couldn't load the dashboard.{' '}
        <button className="text-sky-600 underline" onClick={() => refetch()}>
          Retry
        </button>
      </div>
    );

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Dashboard</h1>
        <p className="text-sm text-[var(--text-muted)]">Pipeline health, satisfaction and next actions. AI insights carry an “AI preview” badge (specs/15).</p>
      </div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard label="Open pipeline" value={formatPHP(data.forecast.open_centavos)} sub={`${data.forecast.count} open opps`} />
        <KpiCard label="Weighted forecast" value={formatPHP(data.forecast.weighted_centavos)} sub="Value × probability" />
        <KpiCard label="NPS avg" value={data.nps_avg ?? '—'} sub={`${data.nps_count} responses`} />
        <KpiCard label="Win rate (90d)" value={data.win_rate_90d !== null && data.win_rate_90d !== undefined ? `${data.win_rate_90d}%` : '—'} sub={data.avg_cycle_days !== null && data.avg_cycle_days !== undefined ? `Avg cycle ${data.avg_cycle_days}d` : 'Close deals to unlock'} />
      </div>

      <div className="card p-4">
        <div className="mb-2 flex items-center gap-2">
          <h2 className="font-semibold">Forecast: weighted vs AI-adjusted</h2>
          <AiBadge />
        </div>
        <ForecastBar weighted={data.forecast.weighted_centavos} adjusted={data.forecast.ai_adjusted_centavos} open={data.forecast.open_centavos} />
        <p className="mt-1 text-xs text-[var(--text-muted)]">AI-adjusted applies the recency/activity multiplier per deal. Rules-based v1 — verify before board use.</p>
      </div>

      {(data.at_risk ?? []).length === 0 ? (
        <EmptyState title="No at-risk clients" hint="Healthy engagement across your book. Log activity to unlock AI insights." />
      ) : (
        <div className="card p-4">
          <div className="mb-2 flex items-center gap-2">
            <h2 className="font-semibold">At-risk clients</h2>
            <AiBadge />
          </div>
          <ul className="flex flex-col gap-2 text-sm">
            {data.at_risk.map((c: { client_id: number; client_name: string; owner_name?: string; level: string; drivers: string[] }) => (
              <li key={c.client_id} className="flex flex-wrap items-center gap-2 border-b border-[var(--border)] pb-2 last:border-0">
                <StatusBadge value={c.level} />
                <span className="font-medium">{c.client_name}</span>
                <span className="text-xs text-[var(--text-muted)]" title={c.drivers.join('; ')}>{c.drivers.join(' · ')}</span>
                <span className="ml-auto flex items-center gap-2">
                  <FeedbackThumbs insightKey={`at_risk:${c.client_id}`} />
                  <Link to="/followups" className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">Create follow-up</Link>
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}

      {(data.leaderboard ?? []).length > 0 && (
        <div className="card p-4">
          <h2 className="mb-2 font-semibold">Top closers (won value)</h2>
          <ul className="flex flex-col gap-1 text-sm">
            {data.leaderboard.map((r: { owner_id: number; owner_name?: string; deals: number; value_centavos: number }) => (
              <li key={r.owner_id} className="flex items-center justify-between border-b border-[var(--border)] pb-1 last:border-0">
                <span>{r.owner_name ?? `#${r.owner_id}`} <span className="text-xs text-[var(--text-muted)]">· {r.deals} deals</span></span>
                <span className="font-semibold tabular-nums">{formatPHP(r.value_centavos)}</span>
              </li>
            ))}
          </ul>
        </div>
      )}

      {data.next_best_actions.length === 0 ? (
        <EmptyState title="All clear" hint="No overdue follow-ups or stale clients. Log activity to unlock AI insights." />
      ) : (
        <div className="card p-4">
          <div className="mb-2 flex items-center gap-2">
            <h2 className="font-semibold">Next best actions</h2>
            <AiBadge />
          </div>
          <ul className="flex flex-col gap-2 text-sm">
            {data.next_best_actions.map((a: { kind: string; title: string; link: string }, i: number) => (
              <li key={i} className="flex items-center justify-between gap-2 border-b border-[var(--border)] pb-2 last:border-0">
                <Link to={a.link} className="text-sky-700 dark:text-sky-300">{a.title}</Link>
                <FeedbackThumbs insightKey={`nba:${a.kind}:${i}`} />
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

function ForecastBar({ weighted, adjusted, open }: { weighted: number; adjusted: number; open: number }) {
  const max = Math.max(open, weighted, adjusted, 1);
  const row = (label: string, value: number, cls: string) => (
    <div className="flex items-center gap-2 text-sm">
      <span className="w-28 shrink-0 text-xs text-[var(--text-muted)]">{label}</span>
      <div className="h-3 flex-1 overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
        <div className={`h-full rounded ${cls}`} style={{ width: `${(value / max) * 100}%` }} />
      </div>
      <span className="w-24 shrink-0 text-right font-semibold tabular-nums">{formatPHP(value)}</span>
    </div>
  );
  return (
    <div className="flex flex-col gap-1.5">
      {row('Open', open, 'bg-slate-300 dark:bg-slate-600')}
      {row('Weighted', weighted, 'bg-sky-500')}
      {row('AI-adjusted', adjusted, 'bg-violet-500')}
    </div>
  );
}
