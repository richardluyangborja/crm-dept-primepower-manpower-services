import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { useToast } from '../components/ui/Toaster';

interface Lead {
  id: number;
  company_name: string;
  contact_name: string;
  contact_email: string | null;
  contact_phone: string | null;
  source: string | null;
  status: string;
  score: number;
}

interface Client {
  id: number;
  name: string;
  industry: string | null;
  address_city: string | null;
  status: string;
  contact_phone: string | null;
  contacts?: { id: number; full_name: string; email: string | null; phone: string | null; is_primary: boolean }[];
}

interface JobOrder {
  id: number;
  ref: string;
  title: string;
  headcount: number | null;
  value_centavos: number;
  status: string;
  next_status: string | null;
  invoice_ref: string | null;
}

interface ClientOps {
  deployment: { deployed?: number; site?: string; mock?: boolean };
  billing: { outstanding_centavos?: number; status?: string; mock?: boolean };
  job_orders: { count: number; active: number; by_status: Record<string, number> };
  mock: boolean;
}

const JO_STAGES = ['draft', 'staffed', 'deployed', 'billed'];

const LEAD_STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted'];

function ScoreBar({ v }: { v: number }) {
  return (
    <span className="flex items-center gap-2">
      <span className="h-2 w-16 overflow-hidden rounded bg-slate-200 dark:bg-slate-700">
        <span className="block h-full bg-sky-500" style={{ width: `${v}%` }} title={`Lead score ${v}/100`} />
      </span>
      <span className="tabular-nums">{v}</span>
    </span>
  );
}

export function LeadsPage() {
  const [tab, setTab] = useState<'leads' | 'clients'>('leads');
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const [showNew, setShowNew] = useState(false);
  const [convertId, setConvertId] = useState<number | null>(null);
  const [detailId, setDetailId] = useState<number | null>(null);
  const toast = useToast();
  const qc = useQueryClient();
  const [params] = useSearchParams();

  // Deep link: /leads?client=<id> opens the 360° drawer (win narration lands here).
  useEffect(() => {
    const cid = params.get('client');
    if (cid) {
      setTab('clients');
      setDetailId(Number(cid));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const leadsQ = useQuery({
    queryKey: ['leads', q, status],
    queryFn: async () => (await api.get('/leads', { params: { q: q || undefined, status: status || undefined, per_page: 50 } })).data,
  });
  const clientsQ = useQuery({
    queryKey: ['clients'],
    queryFn: async () => (await api.get('/clients', { params: { per_page: 50 } })).data,
  });
  const detailQ = useQuery({
    queryKey: ['client', detailId],
    queryFn: async () => (await api.get(`/clients/${detailId}`)).data.data as Client,
    enabled: detailId !== null,
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['leads'] });
    qc.invalidateQueries({ queryKey: ['clients'] });
    qc.invalidateQueries({ queryKey: ['dashboard'] });
  };

  const setStatusMut = useMutation({
    mutationFn: async ({ id, st, reason }: { id: number; st: string; reason?: string }) =>
      (await api.put(`/leads/${id}`, { status: st, unqualified_reason: reason })).data,
    onSuccess: () => {
      toast('success', 'Lead status updated.');
      invalidate();
    },
    onError: (e: unknown) => toast('error', apiErr(e, 'Could not update status.')),
  });

  const convertMut = useMutation({
    mutationFn: async ({ id, withOpp }: { id: number; withOpp: boolean }) =>
      (await api.post(`/leads/${id}/convert`, withOpp ? { create_opportunity: true, opportunity_title: undefined } : {})).data,
    onSuccess: (d) => {
      toast('success', `Converted — client #${d.data.client_id} created.`);
      setConvertId(null);
      invalidate();
      setTab('clients');
    },
    onError: (e: unknown) => toast('error', apiErr(e, 'Conversion failed. Maybe already converted?')),
  });

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold">Leads & Clients</h1>
          <p className="text-sm text-[var(--text-muted)]">Capture in under a minute, qualify with scoring, convert to client.</p>
        </div>
        <button onClick={() => setShowNew(true)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">
          + New lead
        </button>
      </div>

      <div className="flex gap-2">
        {(['leads', 'clients'] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`rounded-lg px-4 py-1.5 text-sm font-medium ${tab === t ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100' : 'border border-[var(--border)]'}`}
          >
            {t === 'leads' ? 'Leads' : 'Clients'}
          </button>
        ))}
      </div>

      {tab === 'leads' ? (
        <>
          <div className="flex gap-2">
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search company, contact, email…" className="card flex-1 px-3 py-2 text-sm outline-none" />
            <select value={status} onChange={(e) => setStatus(e.target.value)} className="card px-3 py-2 text-sm" aria-label="Filter by status">
              <option value="">All statuses</option>
              {LEAD_STATUSES.map((s) => (
                <option key={s} value={s}>{s}</option>
              ))}
            </select>
          </div>
          {leadsQ.isLoading ? (
            <p className="text-sm text-[var(--text-muted)]">Loading leads…</p>
          ) : leadsQ.isError ? (
            <div className="card p-6 text-sm">Couldn't load leads. <button className="text-sky-600 underline" onClick={() => leadsQ.refetch()}>Retry</button></div>
          ) : (
            <DataTable<Lead>
              rows={leadsQ.data.data}
              columns={[
                { key: 'co', header: 'Company', render: (r) => <button className="font-medium text-sky-700 dark:text-sky-300" onClick={() => toast('info', `Score ${r.score}/100: +20 PH email, +25 valid +63 phone, +status`)}>{r.company_name}</button> },
                { key: 'ct', header: 'Contact', render: (r) => <span>{r.contact_name}<br /><span className="text-xs text-[var(--text-muted)]">{r.contact_phone ?? r.contact_email}</span></span> },
                { key: 'sc', header: 'Score', render: (r) => <ScoreBar v={r.score} /> },
                { key: 'st', header: 'Status', render: (r) => (
                  <select value={r.status} disabled={r.status === 'converted'} onChange={(e) => {
                    const st = e.target.value;
                    if (st === 'unqualified') {
                      const reason = window.prompt('Why is this lead unqualified? (required)');
                      if (!reason) { e.target.value = r.status; return; }
                      setStatusMut.mutate({ id: r.id, st, reason });
                    } else setStatusMut.mutate({ id: r.id, st });
                  }} className="rounded border border-[var(--border)] bg-transparent px-1 py-0.5 text-xs" aria-label={`Status of ${r.company_name}`}>
                    {LEAD_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                  </select>
                ) },
                { key: 'ac', header: 'Actions', render: (r) => r.status === 'converted'
                  ? <StatusBadge value="converted" />
                  : <button onClick={() => setConvertId(r.id)} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs">Convert →</button> },
              ]}
              empty={<EmptyState title="No leads yet" hint="Capture your first lead — company, contact and a +63 mobile is enough." action={<button onClick={() => setShowNew(true)} className="mt-2 rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">+ New lead</button>} />}
            />
          )}
        </>
      ) : (
        <>
          {clientsQ.isLoading ? (
            <p className="text-sm text-[var(--text-muted)]">Loading clients…</p>
          ) : (
            <DataTable<Client>
              rows={clientsQ.data?.data ?? []}
              columns={[
                { key: 'n', header: 'Client', render: (r) => <button className="font-medium text-sky-700 dark:text-sky-300" onClick={() => setDetailId(r.id)}>{r.name}</button> },
                { key: 'i', header: 'Industry', render: (r) => r.industry ?? '—' },
                { key: 'c', header: 'City', render: (r) => r.address_city ?? '—' },
                { key: 's', header: 'Status', render: (r) => <StatusBadge value={r.status} /> },
              ]}
              empty={<EmptyState title="No clients yet" hint="Convert a qualified lead to create your first client profile." />}
            />
          )}
          {detailId !== null && (
            <div className="card p-4">
              <div className="flex items-center justify-between">
                <h2 className="font-semibold">{detailQ.data?.name ?? 'Client 360°'}</h2>
                <button onClick={() => setDetailId(null)} className="text-sm text-[var(--text-muted)]">Close ✕</button>
              </div>
              {detailQ.isLoading ? <p className="text-sm">Loading profile…</p> : detailQ.data && (
                <div className="mt-2 text-sm">
                  <p><StatusBadge value={detailQ.data.status} /> {detailQ.data.industry} · {detailQ.data.address_city}</p>
                  <h3 className="mt-3 font-medium">Contacts</h3>
                  <ul className="mt-1 flex flex-col gap-1">
                    {detailQ.data.contacts?.map((c) => (
                      <li key={c.id} className="flex justify-between border-b border-[var(--border)] py-1 last:border-0">
                        <span>{c.full_name} {c.is_primary && <span className="text-xs text-sky-600">(primary)</span>}</span>
                        <span className="text-xs text-[var(--text-muted)]">{c.phone ?? c.email}</span>
                      </li>
                    ))}
                  </ul>
                  <ClientOpsCards clientId={detailQ.data.id} />
                  <ClientJourney clientId={detailQ.data.id} />
                </div>
              )}
            </div>
          )}
        </>
      )}

      {showNew && <NewLeadForm onClose={() => setShowNew(false)} onDone={invalidate} />}
      <ConfirmDialog
        open={convertId !== null}
        title="Convert lead to client?"
        body="This creates the client profile with the lead's contact as primary. Optionally also open an opportunity (step 2 manages it after)."
        onCancel={() => setConvertId(null)}
        onConfirm={() => convertId !== null && convertMut.mutate({ id: convertId, withOpp: true })}
      />
    </div>
  );
}

function ClientOpsCards({ clientId }: { clientId: number }) {
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
        <p className="text-xs text-[var(--text-muted)]">Deployed <span title="Mock Dept-2 read-back">Ⓜ</span></p>
        <p className="font-semibold tabular-nums">{ops.deployment.deployed ?? 0} staff</p>
      </div>
      <div className="rounded-lg border border-[var(--border)] p-2">
        <p className="text-xs text-[var(--text-muted)]">AR balance <span title="Mock Dept-5 read-back">Ⓜ</span></p>
        <p className="font-semibold tabular-nums">{formatPHP(ops.billing.outstanding_centavos ?? 0)}</p>
      </div>
      <div className="rounded-lg border border-[var(--border)] p-2">
        <p className="text-xs text-[var(--text-muted)]">Job orders</p>
        <p className="font-semibold tabular-nums">{ops.job_orders.active} active / {ops.job_orders.count}</p>
      </div>
    </div>
  );
}

function ClientJourney({ clientId }: { clientId: number }) {
  const toast = useToast();
  const qc = useQueryClient();
  const jobsQ = useQuery({
    queryKey: ['job-orders', clientId],
    queryFn: async () => (await api.get('/job-orders', { params: { client_id: clientId, per_page: 50 } })).data.data as JobOrder[],
    enabled: clientId > 0,
  });
  const advanceMut = useMutation({
    mutationFn: async (id: number) => (await api.post(`/job-orders/${id}/advance`)).data,
    onSuccess: (d) => {
      toast('success', d.message ?? 'Advanced.');
      qc.invalidateQueries({ queryKey: ['job-orders', clientId] });
      qc.invalidateQueries({ queryKey: ['client-ops', clientId] });
    },
    onError: (e) => toast('error', apiErr(e, 'Could not advance.')),
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
      <h3 className="font-medium">Staffing journey <span className="text-xs font-normal text-[var(--text-muted)]">(mock Dept 1 → 2 → 5)</span></h3>
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
            {j.next_status && (
              <button onClick={() => advanceMut.mutate(j.id)} className="mt-1.5 rounded-lg border border-[var(--border)] px-2 py-1 text-xs">
                Advance → {j.next_status}
              </button>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}

function apiErr(e: unknown, fallback: string): string {
  if (typeof e === 'object' && e !== null && 'response' in e) {
    const r = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
    if (r?.data?.errors) return Object.values(r.data.errors).flat().join(' ');
    if (r?.data?.message) return r.data.message;
  }
  return fallback;
}

function NewLeadForm({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const [f, setF] = useState({ company_name: '', contact_name: '', contact_email: '', contact_phone: '', source: 'referral' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!/^\+63\d{10}$/.test(f.contact_phone) && f.contact_phone) { setErr('Phone must be +639XXXXXXXXX.'); return; }
    setBusy(true);
    setErr('');
    try {
      const r = await api.post('/leads', { ...f, contact_email: f.contact_email || undefined, contact_phone: f.contact_phone || undefined });
      const dup = r.data.meta?.duplicate_warning;
      toast('success', dup ? `Lead created — heads up: possible duplicate ${dup.type} #${dup.id}.` : 'Lead created — qualify it next.');
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not create lead.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">New lead</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">Company + contact + PH mobile is enough. Score is computed automatically.</p>
        <div className="flex flex-col gap-2 text-sm">
          <label>Company *<input required value={f.company_name} onChange={set('company_name')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Contact person *<input required value={f.contact_name} onChange={set('contact_name')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Email<input type="email" value={f.contact_email} onChange={set('contact_email')} placeholder="hrd@company.ph (+20 score)" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Mobile<input value={f.contact_phone} onChange={set('contact_phone')} placeholder="+639XXXXXXXXX (+25 score)" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Source<select value={f.source} onChange={set('source')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            {['referral', 'walk_in', 'website', 'facebook', 'cold_call', 'event'].map((s) => <option key={s} value={s}>{s}</option>)}
          </select></label>
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Saving…' : 'Create lead'}</button>
        </div>
      </form>
    </div>
  );
}
