import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { InfoCallout } from '../components/ui/InfoCallout';
import { KpiCard } from '../components/ui/KpiCard';
import { MemberPicker, MonthNav, PrintButton, currentMonth, downloadCsv, useMembers } from '../components/crm/WorkforceBits';

interface LeaveDay { date: string; status: string; check_in: string | null; kind: string | null }

/** Workforce hub: leave & absence balances (Core-2, read-only mock). */
export function WorkforceLeavePage() {
  const { user, members, canPick } = useMembers();
  const [memberId, setMemberId] = useState<number | null>(null);
  const [month, setMonth] = useState(currentMonth());
  const target = canPick ? (memberId ?? members[0]?.id ?? user?.id ?? null) : (user?.id ?? null);

  const leaveQ = useQuery({
    queryKey: ['hr-leave', target, month],
    queryFn: async () => (await api.get('/hr/leave', { params: { user_id: target ?? undefined, month } })).data.data as {
      user_id: number;
      month: string;
      balances: { vacation: { allowed: number; used: number }; sick: { allowed: number; used: number } };
      leave_days: LeaveDay[];
      mock: boolean;
    },
    enabled: target !== null,
  });
  const b = leaveQ.data?.balances;
  const days = leaveQ.data?.leave_days ?? [];
  const who = members.find((m) => m.id === target);

  const bar = (used: number, allowed: number) => (
    <div className="mt-1 h-2 overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
      <div className="h-full rounded bg-sky-500" style={{ width: `${allowed > 0 ? Math.min(100, (used / allowed) * 100) : 0}%` }} />
    </div>
  );

  const csv = () =>
    downloadCsv(`leave-${who?.name ?? 'me'}-${month}.csv`, ['date', 'kind'],
      days.map((d) => [d.date, d.kind ?? '']));

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-xl font-bold">Workforce · Leave</h1>
        </div>
        <PrintButton />
      </div>
      <InfoCallout lead="Balances, read-only.">
        Leave requests and approvals live in the HR system — this page shows what's left.
      </InfoCallout>
      <div className="flex flex-wrap gap-2">
        <MemberPicker value={target} onChange={setMemberId} members={members} />
        <MonthNav month={month} onChange={setMonth} />
        <button onClick={csv} className="rounded-lg border border-[var(--border)] px-3 py-2 text-sm font-medium">CSV</button>
      </div>
      {who && <p className="text-sm text-[var(--text-muted)]">Showing <strong className="text-inherit">{who.name}</strong> · {who.role.replace('_', ' ')}</p>}
      {leaveQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading leave…</p>
        : leaveQ.isError ? <div className="card p-6 text-sm">Couldn't load leave. <button className="text-sky-600 underline" onClick={() => leaveQ.refetch()}>Retry</button></div>
        : b && (
          <>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <KpiCard label="Vacation left" value={`${b.vacation.allowed - b.vacation.used} days`} sub={`${b.vacation.used} of ${b.vacation.allowed} used`} />
              <KpiCard label="Sick left" value={`${b.sick.allowed - b.sick.used} days`} sub={`${b.sick.used} of ${b.sick.allowed} used`} />
            </div>
            <div className="card p-4">
              <h2 className="mb-2 font-semibold">Year usage</h2>
              <p className="text-xs text-[var(--text-muted)]">Vacation {b.vacation.used}/{b.vacation.allowed}</p>
              {bar(b.vacation.used, b.vacation.allowed)}
              <p className="mt-2 text-xs text-[var(--text-muted)]">Sick {b.sick.used}/{b.sick.allowed}</p>
              {bar(b.sick.used, b.sick.allowed)}
            </div>
            <DataTable<LeaveDay & { id: string }>
              rows={days.map((d) => ({ ...d, id: d.date }))}
              columns={[
                { key: 'd', header: 'Date', render: (r) => new Date(r.date + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' }) },
                { key: 'k', header: 'Kind', render: (r) => <span className="capitalize">{r.kind ?? '—'}</span> },
              ]}
              empty={<EmptyState title="No leave this month" hint="Leave days taken in this month will list here." />}
            />
          </>
        )}
    </div>
  );
}
