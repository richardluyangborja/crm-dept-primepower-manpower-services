import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { InfoCallout } from '../components/ui/InfoCallout';
import { KpiCard } from '../components/ui/KpiCard';
import { StatusBadge } from '../components/ui/StatusBadge';
import { MemberPicker, MonthNav, PrintButton, currentMonth, downloadCsv, useMembers } from '../components/crm/WorkforceBits';

interface AttDay { date: string; status: string; check_in: string | null; kind: string | null }
interface AttSummary { present: number; late: number; absent: number; leave: number; workdays: number; attendance_pct: number | null; punctuality_pct: number | null; avg_late_mins: number }

const DOT: Record<string, string> = { present: 'bg-green-500', late: 'bg-amber-500', absent: 'bg-red-500', leave: 'bg-sky-500' };

/** Workforce hub: timekeeping attendance (Core-2, read-only mock). */
export function WorkforceAttendancePage() {
  const { user, members, canPick } = useMembers();
  const [memberId, setMemberId] = useState<number | null>(null);
  const [month, setMonth] = useState(currentMonth());
  const [page, setPage] = useState(1);
  const PER_PAGE = 15;
  const target = canPick ? (memberId ?? members[0]?.id ?? user?.id ?? null) : (user?.id ?? null);

  const attQ = useQuery({
    queryKey: ['hr-attendance', target, month],
    queryFn: async () => (await api.get('/hr/attendance', { params: { user_id: target ?? undefined, month } })).data.data as {
      user_id: number; days: AttDay[]; summary: AttSummary; mock: boolean;
    },
    enabled: target !== null,
  });
  const days = attQ.data?.days ?? [];
  const s = attQ.data?.summary;
  const who = members.find((m) => m.id === target);

  const csv = () =>
    downloadCsv(`attendance-${who?.name ?? 'me'}-${month}.csv`, ['date', 'status', 'check_in', 'kind'],
      days.map((d) => [d.date, d.status, d.check_in ?? '', d.kind ?? '']));

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-xl font-bold">Workforce · Attendance</h1>
        </div>
        <PrintButton />
      </div>
      <InfoCallout lead="Timekeeping, read-only.">
        Daily presence via HR — schedules and corrections live in the HR system, not here.
      </InfoCallout>
      <div className="flex flex-wrap gap-2">
        <MemberPicker value={target} onChange={(id) => { setMemberId(id); setPage(1); }} members={members} />
        <MonthNav month={month} onChange={(m) => { setMonth(m); setPage(1); }} />
        <button onClick={csv} className="rounded-lg border border-[var(--border)] px-3 py-2 text-sm font-medium">CSV</button>
      </div>
      {who && <p className="text-sm text-[var(--text-muted)]">Showing <strong className="text-inherit">{who.name}</strong> · {who.role.replace('_', ' ')}</p>}
      {attQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading attendance…</p>
        : attQ.isError ? <div className="card p-6 text-sm">Couldn't load attendance. <button className="text-sky-600 underline" onClick={() => attQ.refetch()}>Retry</button></div>
        : s && (
          <>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              <KpiCard label="Attendance" value={s.attendance_pct !== null ? `${s.attendance_pct}%` : '—'} sub={`${s.present + s.late} of ${s.workdays} workdays`} />
              <KpiCard label="On time" value={s.punctuality_pct !== null ? `${s.punctuality_pct}%` : '—'} sub={`${s.late} late arrivals`} />
              <KpiCard label="Absent" value={String(s.absent)} sub="unexplained" />
              <KpiCard label="On leave" value={String(s.leave)} sub="vacation + sick" />
            </div>
            <div className="card p-4">
              <h2 className="mb-2 font-semibold">Month at a glance</h2>
              <div className="flex flex-wrap gap-1.5">
                {days.map((d) => (
                  <span key={d.date} title={`${d.date}: ${d.status}${d.check_in ? ` in ${d.check_in}` : ''}${d.kind ? ` (${d.kind})` : ''}`}
                    className={`h-4 w-4 rounded-sm ${DOT[d.status] ?? 'bg-slate-300'}`} />
                ))}
                {days.length === 0 && <p className="text-sm text-[var(--text-muted)]">No workdays this month yet.</p>}
              </div>
              <p className="mt-2 flex flex-wrap gap-3 text-[11px] text-[var(--text-muted)]">
                <span><span className="mr-1 inline-block h-2 w-2 rounded-sm bg-green-500" />present</span>
                <span><span className="mr-1 inline-block h-2 w-2 rounded-sm bg-amber-500" />late</span>
                <span><span className="mr-1 inline-block h-2 w-2 rounded-sm bg-red-500" />absent</span>
                <span><span className="mr-1 inline-block h-2 w-2 rounded-sm bg-sky-500" />leave</span>
              </p>
            </div>
            <DataTable<AttDay & { id: string }>
              rows={days.slice((page - 1) * PER_PAGE, page * PER_PAGE).map((d) => ({ ...d, id: d.date }))}
              pagination={{ page, perPage: PER_PAGE, total: days.length, onPage: setPage }}
              columns={[
                { key: 'd', header: 'Date', render: (r) => new Date(r.date + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' }) },
                { key: 's', header: 'Status', render: (r) => <StatusBadge value={r.status} /> },
                { key: 'c', header: 'Check-in', render: (r) => <span className="tabular-nums">{r.check_in ?? '—'}</span> },
                { key: 'k', header: 'Kind', render: (r) => r.kind ?? '—' },
              ]}
              empty={<EmptyState title="No workdays" hint="Weekends and future days don't count." />}
            />
            <p className="text-xs text-[var(--text-muted)]">Performance context lives in <Link to="/workforce/performance" className="text-sky-700 hover:underline dark:text-sky-300">Performance</Link>.</p>
          </>
        )}
    </div>
  );
}
