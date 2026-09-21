import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../lib/apiClient';
import { formatPHP } from '../../lib/format';
import { StatusBadge } from '../ui/StatusBadge';

export interface ClientOps {
  deployment: { deployed?: number; site?: string; mock?: boolean };
  billing: { outstanding_centavos?: number; status?: string; mock?: boolean };
  fulfillment: { required: number; deployed: number; remaining: number; pct: number | null; status: string; mock?: boolean };
  job_orders: { count: number; active: number; by_status: Record<string, number> };
  mock: boolean;
}

export interface JobOrder {
  id: number;
  ref: string;
  title: string;
  headcount: number | null;
  value_centavos: number;
  status: string;
  next_status: string | null;
  invoice_ref: string | null;
}

export const JO_STAGES = ['draft', 'staffed', 'deployed', 'billed'];

export function apiErr(e: unknown, fallback: string): string {
  if (typeof e === 'object' && e !== null && 'response' in e) {
    const r = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
    if (r?.data?.errors) return Object.values(r.data.errors).flat().join(' ');
    if (r?.data?.message) return r.data.message;
  }
  return fallback;
}

export function ClientOpsCards({ clientId }: { clientId: number }) {
  const opsQ = useQuery({
    queryKey: ['client-ops', clientId],
    queryFn: async () => (await api.get(`/clients/${clientId}/operations`)).data.data as ClientOps,
    enabled: clientId > 0,
  });
  if (opsQ.isLoading || !opsQ.data) return null;
  const ops = opsQ.data;
  return (
    <div className="mt-3 grid grid-cols-3 gap-2 text-sm">
      <div className="rounded-lg border border-[var(--border)] p-2">
        <p className="text-xs text-[var(--text-muted)]">Deployed <span title="Read-back from Client Management">ⓘ</span></p>
        <p className="font-semibold tabular-nums"><Link to={`/pipeline/staffing?client=${clientId}`} className="text-sky-700 hover:underline dark:text-sky-300">{ops.deployment.deployed ?? 0} staff</Link></p>
      </div>
      <div className="rounded-lg border border-[var(--border)] p-2">
        <p className="text-xs text-[var(--text-muted)]">AR balance <span title="Read-back from Finance">ⓘ</span></p>
        <p className="font-semibold tabular-nums">{formatPHP(ops.billing.outstanding_centavos ?? 0)}</p>
      </div>
      <div className="rounded-lg border border-[var(--border)] p-2">
        <p className="text-xs text-[var(--text-muted)]">Job orders</p>
        <p className="font-semibold tabular-nums"><Link to="/operations" className="text-sky-700 hover:underline dark:text-sky-300">{ops.job_orders.active} active / {ops.job_orders.count}</Link></p>
      </div>
    </div>
  );
}

export function ClientJourney({ clientId }: { clientId: number }) {
  const jobsQ = useQuery({
    queryKey: ['job-orders', clientId],
    queryFn: async () => (await api.get('/job-orders', { params: { client_id: clientId, per_page: 50 } })).data.data as JobOrder[],
    enabled: clientId > 0,
  });

  const jobs = jobsQ.data ?? [];
  if (jobsQ.isLoading) return <p className="mt-3 text-xs">Loading journey…</p>;
  if (jobs.length === 0) {
    return (
      <div className="mt-3 rounded-lg border border-dashed border-[var(--border)] p-3 text-xs text-[var(--text-muted)]">
        No job orders yet — win an opportunity and the staffing journey starts here automatically.
      </div>
    );
  }
  return (
    <div className="mt-3">
      <div className="flex items-center justify-between">
        <h3 className="font-medium">Staffing journey <span className="text-xs font-normal text-[var(--text-muted)]">(via Client Management — read-only)</span></h3>
        <Link to={`/pipeline?client=${clientId}`} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs">+ New deal</Link>
      </div>
      <ul className="mt-1 flex flex-col gap-2">
        {jobs.map((j) => (
          <li key={j.id} className="rounded-lg border border-[var(--border)] p-2.5 text-sm">
            <div className="flex items-center justify-between gap-2">
              <span className="font-medium">{j.ref} · {j.title}</span>
              <StatusBadge value={j.status} />
            </div>
            <div className="mt-1.5 flex items-center gap-1" aria-label={`Stage ${j.status}`}>
              {JO_STAGES.map((s) => (
                <span key={s} title={s} className={`h-1.5 flex-1 rounded ${JO_STAGES.indexOf(s) <= JO_STAGES.indexOf(j.status) ? 'bg-sky-500' : 'bg-slate-200 dark:bg-slate-700'}`} />
              ))}
            </div>
            <p className="mt-1 text-xs text-[var(--text-muted)]">
              {j.headcount !== null ? `${j.headcount} headcount` : 'Headcount estimating'} · {formatPHP(j.value_centavos)}
              {j.invoice_ref ? ` · ${j.invoice_ref}` : ''}
            </p>
          </li>
        ))}
      </ul>
    </div>
  );
}
