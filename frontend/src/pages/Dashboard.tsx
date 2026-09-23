import { Link } from 'react-router-dom';
import { useQueries, useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { KpiCard } from '../components/ui/KpiCard';
import { useSession } from '../store/session';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { AiBadge, FeedbackThumbs } from '../components/crm/InsightBits';
import { TrendsCard, StageDonut, useStageLabels, type MonthPoint } from '../components/crm/TrendCharts';

export function DashboardPage() {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await api.get('/dashboard/summary')).data.data,
  });
  const stageLabels = useStageLabels();

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
        <p className="text-sm text-[var(--text-muted)]">The whole client lifecycle — sales, contracts, staffing, and collections at a glance.</p>
      </div>
      <NarrativeStrip data={data} />
      <LifecycleStrip />
      <TeamPulse />
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard label="Open pipeline" value={formatPHP(data.forecast.open_centavos)} sub={`${data.forecast.count} open opps`} href="/pipeline" />
        <KpiCard label="Weighted forecast" value={formatPHP(data.forecast.weighted_centavos)} sub="Value × probability" href="/pipeline" />
        <KpiCard label="Satisfaction" value={data.nps_avg ?? '—'} sub={`Net Promoter Score · ${data.nps_count} responses`} href="/surveys" />
        <KpiCard label="Win rate (90d)" value={data.win_rate_90d !== null && data.win_rate_90d !== undefined ? `${data.win_rate_90d}%` : '—'} sub={data.avg_cycle_days !== null && data.avg_cycle_days !== undefined ? `Avg cycle ${data.avg_cycle_days}d` : 'Close deals to unlock'} href="/reports" />
      </div>

      <div className="grid gap-3 xl:grid-cols-2">
        <StageDonut byStage={data.by_stage ?? []} labels={stageLabels} />
        <div className="card p-4">
          <div className="mb-2 flex items-center justify-between">
            <h2 className="font-semibold">Forecast: weighted vs adjusted</h2>
            <Link to="/pipeline" className="text-xs text-sky-700 hover:underline dark:text-sky-300">Open board →</Link>
          </div>
          <ForecastBar weighted={data.forecast.weighted_centavos} adjusted={data.forecast.ai_adjusted_centavos} open={data.forecast.open_centavos} />
        </div>
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
            {data.at_risk.map((c: { client_id: string; client_name: string; owner_name?: string; level: string; drivers: string[] }) => (
              <li key={c.client_id} className="flex flex-wrap items-center gap-2 border-b border-[var(--border)] pb-2 last:border-0">
                <StatusBadge value={c.level} />
                <Link to={`/clients/${c.client_id}`} className="font-medium text-sky-700 hover:underline dark:text-sky-300">{c.client_name}</Link>
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

      <TrendsCard trend={(data.trends?.monthly ?? []) as MonthPoint[]} />

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

interface LifecycleContract {
  id: string; ref: string; client_id: string; client_name?: string;
  monthly_billing_centavos: number | null; start_date: string | null;
  contract_months: number | null; status: string;
}

/** Lifecycle strip (specs/04): contract + Client Management + Finance summaries, read-only. */
/** Team pulse (specs/11): role-aware people charts linking into Workforce. */
function TeamPulse() {
  const { user } = useSession();
  const month = new Date().toISOString().slice(0, 7);
  const dirQ = useQuery({
    queryKey: ['hr-directory', 'pulse'],
    queryFn: async () => (await api.get('/hr/directory', { params: { per_page: 100 } })).data.data as {
      id: number; name: string; role: string;
    }[],
  });
  const members = (dirQ.data ?? []).filter((m) => ['manager', 'sales_rep'].includes(m.role)).slice(0, 12);
  const perfQ = useQueries({
    queries: members.map((m) => ({
      queryKey: ['hr-performance', m.id, month, 'pulse'],
      queryFn: async () => ({
        member: m,
        perf: (await api.get('/hr/performance', { params: { user_id: m.id, month } })).data.data as {
          composite: number | null; crm: { won_value_centavos: number }; hr: { attendance_pct: number | null };
        },
      }),
      staleTime: 60000,
    })),
  });
  const ranked: { member: { id: number; name: string }; perf: { crm: { won_value_centavos: number }; hr: { attendance_pct: number | null }; composite: number | null } }[] = [];
  for (const r of perfQ) {
    if (r.data?.perf) ranked.push(r.data as (typeof ranked)[number]);
  }
  ranked.sort((a, b) => (b.perf.crm.won_value_centavos ?? 0) - (a.perf.crm.won_value_centavos ?? 0));
  const maxWon = Math.max(1, ...ranked.map((r) => r.perf.crm.won_value_centavos ?? 0));
  const isRep = user?.role === 'sales_rep';
  const mine = ranked.find((r) => r.member.id === user?.id);

  return (
    <div className="card p-4">
      <div className="mb-2 flex items-center justify-between">
        <h2 className="font-semibold">Team pulse <span className="text-xs font-normal text-[var(--text-muted)]">via HR</span></h2>
        <Link to="/workforce/performance" className="text-xs text-sky-700 hover:underline dark:text-sky-300">Open Workforce →</Link>
      </div>
      {dirQ.isLoading || perfQ.some((r) => r.isLoading) ? (
        <p className="text-sm text-[var(--text-muted)]">Reading the team…</p>
      ) : ranked.length === 0 ? (
        <p className="text-sm text-[var(--text-muted)]">No measured teammates in scope.</p>
      ) : isRep && mine ? (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <KpiCard label="My composite" value={mine.perf.composite !== null ? String(mine.perf.composite) : '—'} sub="blended 0–100" href="/workforce/performance" />
          <KpiCard label="My won value" value={formatPHP(mine.perf.crm.won_value_centavos)} sub="this month" href="/pipeline" />
          <KpiCard label="My attendance" value={mine.perf.hr.attendance_pct !== null ? `${mine.perf.hr.attendance_pct}%` : '—'} sub="this month" href="/workforce/attendance" />
        </div>
      ) : (
        <div className="flex flex-col gap-1.5">
          {ranked.slice(0, 5).map(({ member, perf }) => (
            <Link key={member.id} to="/workforce/performance" className="flex items-center gap-2 text-sm">
              <span className="w-36 shrink-0 truncate">{member.name}</span>
              <span className="h-3 flex-1 overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
                <span className="block h-full rounded bg-sky-500" style={{ width: `${((perf.crm.won_value_centavos ?? 0) / maxWon) * 100}%` }} />
              </span>
              <span className="w-24 shrink-0 text-right font-semibold tabular-nums">{formatPHP(perf.crm.won_value_centavos)}</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}

function LifecycleStrip() {  const contractsQ = useQuery({
    queryKey: ['contracts', 'dashboard'],
    queryFn: async () => (await api.get('/contracts', { params: { status: 'active', per_page: 100 } })).data.data as LifecycleContract[],
  });
  const staffingQ = useQuery({
    queryKey: ['staffing', 'dashboard'],
    queryFn: async () => (await api.get('/staffing')).data.data as { meta: { total_deployed: number; total_job_orders: number } },
  });
  const financeQ = useQuery({
    queryKey: ['finance-summary', 'dashboard'],
    queryFn: async () => (await api.get('/finance/summary')).data.data as { outstanding_total_centavos: number },
  });

  const contracts = contractsQ.data ?? [];
  const monthly = contracts.reduce((a, c) => a + (c.monthly_billing_centavos ?? 0), 0);
  const renewals = contracts
    .map((c) => {
      if (!c.start_date || !c.contract_months) return null;
      const end = new Date(c.start_date);
      end.setMonth(end.getMonth() + c.contract_months);
      return { ...c, end };
    })
    .filter((c): c is LifecycleContract & { end: Date } => !!c && c.end >= new Date() && c.end <= new Date(Date.now() + 60 * 864e5))
    .sort((a, b) => a.end.getTime() - b.end.getTime())
    .slice(0, 3);
  const loading = contractsQ.isLoading || staffingQ.isLoading || financeQ.isLoading;

  return (
    <div className="flex flex-col gap-3">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard label="Active contracts" value={loading ? '…' : String(contracts.length)} sub="commercial relationships live now" href="/pipeline/contracts" />
        <KpiCard label="Monthly recurring" value={loading ? '…' : formatPHP(monthly)} sub="contracted billing per month" href="/pipeline/finance" />
        <KpiCard label="Deployed staff" value={loading ? '…' : String(staffingQ.data?.meta.total_deployed ?? '—')} sub="via Client Management" href="/pipeline/staffing" />
        <KpiCard label="Outstanding AR" value={loading ? '…' : formatPHP(financeQ.data?.outstanding_total_centavos ?? 0)} sub="open balances" href="/pipeline/finance" />
      </div>
      {renewals.length > 0 && (
        <div className="card p-4">
          <h2 className="mb-2 font-semibold">Renewals approaching <span className="text-xs font-normal text-[var(--text-muted)]">(next 60 days)</span></h2>
          <ul className="flex flex-col gap-1 text-sm">
            {renewals.map((c) => (
              <li key={c.id} className="flex items-center justify-between gap-2 border-b border-[var(--border)] pb-1 last:border-0">
                <Link to={`/clients/${c.client_id}`} className="font-medium text-sky-700 hover:underline dark:text-sky-300">{c.client_name ?? c.ref}</Link>
                <span className="text-xs text-[var(--text-muted)] tabular-nums">{c.ref} · ends {c.end.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

function NarrativeStrip({ data }: { data: {  forecast: { open_centavos: number; weighted_centavos: number; count: number };
  nps_avg?: number | null;
  at_risk?: { client_name: string; level: string }[];
  next_best_actions: { kind: string }[];
} }) {
  const overdue = data.next_best_actions.filter((a) => a.kind === 'followup_overdue').length;
  const risks = data.at_risk ?? [];
  const topRisk = risks.find((c) => c.level === 'high') ?? risks[0];
  const sentences = [
    `${data.forecast.count} open deals worth ${formatPHP(data.forecast.open_centavos)} (${formatPHP(data.forecast.weighted_centavos)} weighted).`,
    data.nps_avg !== null && data.nps_avg !== undefined
      ? `Client sentiment sits at ${data.nps_avg} out of 10.`
      : 'No satisfaction data yet — send a survey to unlock it.',
    overdue > 0 ? `${overdue} overdue follow-up${overdue === 1 ? '' : 's'} need${overdue === 1 ? 's' : ''} clearing.` : 'Follow-ups are under control.',
    topRisk ? `Top concern: ${topRisk.client_name} (${topRisk.level} risk).` : 'No at-risk clients right now.',
  ];
  return (
    <div className="card border-l-4 border-l-sky-500 p-4">
      <p className="text-sm leading-relaxed"><span className="font-semibold">Today at Primepower: </span>{sentences.join(' ')}</p>
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
