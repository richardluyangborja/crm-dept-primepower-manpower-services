import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { InfoCallout } from '../components/ui/InfoCallout';
import { StatusBadge } from '../components/ui/StatusBadge';
import { PrintButton, downloadCsv, type DirectoryMember } from '../components/crm/WorkforceBits';

/** Workforce hub: employee directory (Core-2 employee info, read-only). */
export function WorkforceDirectoryPage() {
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const PER_PAGE = 15;

  const dirQ = useQuery({
    queryKey: ['hr-directory', q, page],
    queryFn: async () => (await api.get('/hr/directory', { params: { q: q || undefined, page, per_page: PER_PAGE } })).data,
  });
  const rows: DirectoryMember[] = dirQ.data?.data ?? [];

  const csv = () =>
    downloadCsv('workforce-directory.csv', ['name', 'email', 'role', 'team', 'active', 'last_login'], rows.map((r) => [
      r.name, r.email, r.role, r.team_name ?? '', r.is_active ? 'yes' : 'no', r.last_login_at ?? '',
    ]));

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-xl font-bold">Workforce · Directory</h1>
        </div>
        <PrintButton />
      </div>
      <InfoCallout lead="Who works here, read-only.">
        Employee info via HR — roles, teams, and activity. Headcount changes stay in user management.
      </InfoCallout>
      <div className="flex gap-2">
        <input value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} placeholder="Search name or email…" className="card flex-1 px-3 py-2 text-sm outline-none" />
        <button onClick={csv} className="rounded-lg border border-[var(--border)] px-3 py-2 text-sm font-medium">CSV</button>
      </div>
      {dirQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading directory…</p>
        : dirQ.isError ? <div className="card p-6 text-sm">Couldn't load the directory. <button className="text-sky-600 underline" onClick={() => dirQ.refetch()}>Retry</button></div>
        : (
          <DataTable<DirectoryMember & { id: number }>
            rows={rows}
            pagination={{ page, perPage: PER_PAGE, total: dirQ.data?.meta?.total ?? rows.length, onPage: setPage }}
            columns={[
              { key: 'n', header: 'Person', render: (r) => <span className="font-medium">{r.name}<br /><span className="text-xs font-normal text-[var(--text-muted)]">{r.email}</span></span> },
              { key: 'r', header: 'Role', render: (r) => <StatusBadge value={r.role} /> },
              { key: 't', header: 'Team', render: (r) => r.team_name ?? '—' },
              { key: 'a', header: 'Active', render: (r) => (r.is_active ? 'Yes' : 'No') },
              { key: 'l', header: 'Last login', render: (r) => (r.last_login_at ? new Date(r.last_login_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) : '—') },
            ]}
            empty={<EmptyState title="Nobody here" hint="Nobody in scope matches that search." />}
          />
        )}
    </div>
  );
}
