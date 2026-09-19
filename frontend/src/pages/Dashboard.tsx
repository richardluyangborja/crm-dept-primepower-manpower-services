import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { KpiCard } from '../components/ui/KpiCard';
import { EmptyState } from '../components/ui/EmptyState';

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
        <KpiCard label="Next actions" value={String(data.next_best_actions.length)} sub="Suggested by rules engine" />
      </div>
      {data.next_best_actions.length === 0 ? (
        <EmptyState title="All clear" hint="No overdue follow-ups or stale clients. Log activity to unlock AI insights." />
      ) : (
        <div className="card p-4">
          <h2 className="mb-2 font-semibold">Next best actions</h2>
          <ul className="flex flex-col gap-2 text-sm">
            {data.next_best_actions.map((a: { kind: string; title: string; link: string }, i: number) => (
              <li key={i} className="flex items-center justify-between border-b border-[var(--border)] pb-2 last:border-0">
                <span>{a.title}</span>
                <span className="rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-800">AI preview</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
