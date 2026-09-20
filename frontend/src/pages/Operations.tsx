import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { EmptyState } from '../components/ui/EmptyState';

interface JobOrder {
  id: number;
  ref: string;
  title: string;
  client_id: number;
  client_name?: string;
  headcount: number | null;
  value_centavos: number;
  status: string;
  invoice_ref: string | null;
}

const JO_FLOW = ['draft', 'staffed', 'deployed', 'billed'] as const;

/**
 * Phase 2C — read-only Core-1 Operations board (specs/18).
 * Visibility only: staffing stages advance on the client timeline,
 * never here. Everything is mock-labeled.
 */
export function OperationsPage() {
  const jobsQ = useQuery({
    queryKey: ['job-orders-all'],
    queryFn: async () => (await api.get('/job-orders', { params: { per_page: 100 } })).data.data as JobOrder[],
  });
  const rows = jobsQ.data ?? [];
  const byStatus = (s: string) => rows.filter((r) => r.status === s);
  const totalHeadcount = rows
    .filter((r) => r.status === 'deployed')
    .reduce((a, r) => a + (r.headcount ?? 0), 0);

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Operations</h1>
        <p className="text-sm text-[var(--text-muted)]">
          Read-only view into mock Core-1 execution: job orders across clients and deployed headcount.
          Staffing advances on each client's timeline — nothing here mutates state.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="card p-4">
          <p className="text-xs uppercase text-[var(--text-muted)]">Active job orders</p>
          <p className="text-2xl font-bold tabular-nums">{rows.filter((r) => r.status !== 'billed').length}</p>
        </div>
        <div className="card p-4">
          <p className="text-xs uppercase text-[var(--text-muted)]">Deployed headcount Ⓜ</p>
          <p className="text-2xl font-bold tabular-nums">{totalHeadcount}</p>
        </div>
        <div className="card p-4">
          <p className="text-xs uppercase text-[var(--text-muted)]">Billed (completed)</p>
          <p className="text-2xl font-bold tabular-nums">{byStatus('billed').length}</p>
        </div>
      </div>

      {jobsQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading operations…</p>
        : jobsQ.isError ? <div className="card p-6 text-sm">Couldn't load operations. <button className="text-sky-600 underline" onClick={() => jobsQ.refetch()}>Retry</button></div>
        : rows.length === 0 ? <EmptyState title="No job orders yet" hint="Win a deal on the Pipeline board and the staffing journey starts automatically." action={<Link to="/pipeline" className="mt-2 inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Go to Pipeline</Link>} />
        : (
          <div className="flex gap-3 overflow-x-auto pb-2">
            {JO_FLOW.map((s) => {
              const col = byStatus(s);
              return (
                <div key={s} className="w-64 shrink-0 rounded-xl border border-[var(--border)] bg-[var(--bg-card)] p-2">
                  <div className="flex items-center justify-between px-1 py-1">
                    <p className="text-xs font-bold uppercase">{s} <span className="text-[var(--text-muted)]">{col.length}</span></p>
                  </div>
                  <div className="flex flex-col gap-2">
                    {col.map((j) => (
                      <div key={j.id} className="rounded-lg border border-[var(--border)] bg-[var(--bg-app)] p-2.5 text-sm">
                        <p className="font-medium">{j.ref}</p>
                        <p className="truncate text-xs">{j.title}</p>
                        <Link to={`/clients/${j.client_id}`} className="text-xs text-sky-700 dark:text-sky-300">{j.client_name ?? `#${j.client_id}`} →</Link>
                        <p className="mt-1 text-xs text-[var(--text-muted)] tabular-nums">
                          {j.headcount !== null ? `${j.headcount} headcount · ` : ''}{formatPHP(j.value_centavos)}
                        </p>
                      </div>
                    ))}
                    {col.length === 0 && <p className="px-1 py-4 text-center text-xs text-[var(--text-muted)]">Empty</p>}
                  </div>
                </div>
              );
            })}
          </div>
        )}
    </div>
  );
}
