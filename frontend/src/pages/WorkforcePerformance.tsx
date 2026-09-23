import { useState } from 'react';
import { useQueries, useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { InfoCallout } from '../components/ui/InfoCallout';
import { KpiCard } from '../components/ui/KpiCard';
import { hasRole } from '../store/session';
import { MemberPicker, MonthNav, PrintButton, currentMonth, downloadCsv, useMembers } from '../components/crm/WorkforceBits';

interface Perf {
  user_id: number;
  crm: { won_value_centavos: number; touches: number; followups_done: number; completion_pct: number | null; overdue: number };
  hr: { attendance_pct: number | null; punctuality_pct: number | null; rating: number | null };
  parts: Record<string, number | null>;
  composite: number | null;
  mock: boolean;
}

/** Workforce hub: performance composites (Core-2, blended mock). Managers+ get the ranking. */
export function WorkforcePerformancePage() {
  const { user, members, canPick } = useMembers();
  const [memberId, setMemberId] = useState<number | null>(null);
  const [month, setMonth] = useState(currentMonth());
  const target = canPick ? (memberId ?? members[0]?.id ?? user?.id ?? null) : (user?.id ?? null);
  const showRanking = hasRole(user, 'manager', 'admin', 'superadmin');

  const perfQ = useQuery({
    queryKey: ['hr-performance', target, month],
    queryFn: async () => (await api.get('/hr/performance', { params: { user_id: target ?? undefined, month } })).data.data as Perf,
    enabled: target !== null,
  });
  const p = perfQ.data;
  const who = members.find((m) => m.id === target);

  const rankQ = useQueries({
    queries: (showRanking ? members.filter((m) => ['manager', 'sales_rep'].includes(m.role)).slice(0, 20) : []).map((m) => ({
      queryKey: ['hr-performance', m.id, month],
      queryFn: async () => ({
        member: m,
        perf: (await api.get('/hr/performance', { params: { user_id: m.id, month } })).data.data as Perf,
      }),
      staleTime: 60000,
    })),
  });
  const ranking = rankQ
    .filter((r) => r.data)
    .map((r) => r.data!)
    .sort((a, b) => (b.perf.composite ?? -1) - (a.perf.composite ?? -1));

  const csv = () =>
    downloadCsv(`performance-${month}.csv`,
      ['name', 'role', 'composite', 'won_php', 'touches', 'completion', 'attendance', 'rating'],
      ranking.map(({ member, perf }) => [
        member.name, member.role, perf.composite ?? '',
        (perf.crm.won_value_centavos / 100).toFixed(2), perf.crm.touches,
        perf.crm.completion_pct ?? '', perf.hr.attendance_pct ?? '', perf.hr.rating ?? '',
      ]));

  const partBar = (label: string, v: number | null) => (
    <div className="flex items-center gap-2 text-sm">
      <span className="w-28 shrink-0 text-xs text-[var(--text-muted)]">{label}</span>
      <div className="h-3 flex-1 overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
        <div className="h-full rounded bg-sky-500" style={{ width: `${v ?? 0}%` }} />
      </div>
      <span className="w-14 shrink-0 text-right font-semibold tabular-nums">{v !== null ? `${v}` : '—'}</span>
    </div>
  );

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-xl font-bold">Workforce · Performance</h1>
        </div>
        <PrintButton />
      </div>
      <InfoCallout lead="Real output, HR context.">
        Won value, touches, and follow-up completion come from live CRM data; attendance, punctuality, and ratings arrive via HR. Nothing here can be edited.
      </InfoCallout>
      <div className="flex flex-wrap gap-2">
        <MemberPicker value={target} onChange={setMemberId} members={members} />
        <MonthNav month={month} onChange={setMonth} />
        {showRanking && <button onClick={csv} className="rounded-lg border border-[var(--border)] px-3 py-2 text-sm font-medium">CSV</button>}
      </div>
      {who && <p className="text-sm text-[var(--text-muted)]">Showing <strong className="text-inherit">{who.name}</strong> · {who.role.replace('_', ' ')}</p>}
      {perfQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading performance…</p>
        : perfQ.isError || !p ? <div className="card p-6 text-sm">Couldn't load performance. <button className="text-sky-600 underline" onClick={() => perfQ.refetch()}>Retry</button></div>
        : (
          <>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <KpiCard label="Composite" value={p.composite !== null ? String(p.composite) : '—'} sub="blended 0–100" />
              <KpiCard label="Won value" value={formatPHP(p.crm.won_value_centavos)} sub={`${p.crm.touches} touches logged`} />
              <KpiCard label="HR rating" value={p.hr.rating !== null ? String(p.hr.rating) : '—'} sub={p.hr.attendance_pct !== null ? `${p.hr.attendance_pct}% attendance` : 'no attendance yet'} />
            </div>
            <div className="card flex flex-col gap-1.5 p-4">
              <h2 className="mb-1 font-semibold">How the score builds</h2>
              {partBar('Deals won (35%)', p.parts.won)}
              {partBar('Touches (20%)', p.parts.touches)}
              {partBar('Follow-up completion (20%)', p.parts.completion)}
              {partBar('Attendance (15%)', p.parts.attendance)}
              {partBar('Punctuality (10%)', p.parts.punctuality)}
              <p className="mt-1 text-xs text-[var(--text-muted)]">
                {p.crm.followups_done} follow-ups done · {p.crm.overdue} overdue · {p.crm.completion_pct !== null ? `${p.crm.completion_pct}% completion` : 'no follow-ups yet'}
              </p>
            </div>
          </>
        )}
      {showRanking && (
        <div className="card p-4">
          <h2 className="mb-2 font-semibold">Team ranking <span className="text-xs font-normal text-[var(--text-muted)]">this month</span></h2>
          {rankQ.some((r) => r.isLoading) ? <p className="text-sm text-[var(--text-muted)]">Ranking the team…</p>
            : ranking.length === 0 ? <EmptyState title="Nobody ranked" hint="No measured members in scope." />
            : (
              <DataTable<{ id: number; name: string; role: string; composite: number | null; won: number }>
                rows={ranking.map(({ member, perf }, i) => ({ id: member.id, name: `${i + 1}. ${member.name}`, role: member.role, composite: perf.composite, won: perf.crm.won_value_centavos }))}
                columns={[
                  { key: 'n', header: 'Member', render: (r) => <span className="font-medium">{r.name} <span className="text-xs font-normal text-[var(--text-muted)]">· {r.role.replace('_', ' ')}</span></span> },
                  { key: 'c', header: 'Composite', render: (r) => <span className="font-semibold tabular-nums">{r.composite ?? '—'}</span> },
                  { key: 'w', header: 'Won', render: (r) => <span className="tabular-nums">{formatPHP(r.won)}</span> },
                  { key: 'o', header: '', render: (r) => <button onClick={() => { setMemberId(r.id); window.scrollTo({ top: 0, behavior: 'smooth' }); }} className="text-xs text-sky-700 hover:underline dark:text-sky-300">Details →</button> },
                ]}
                empty={<EmptyState title="Nobody ranked" hint="No measured members in scope." />}
              />
            )}
        </div>
      )}
    </div>
  );
}
