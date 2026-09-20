import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { useToast } from '../components/ui/Toaster';
import { apiErr } from '../components/crm/ClientWidgets';

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
}

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
  const toast = useToast();
  const qc = useQueryClient();

  const leadsQ = useQuery({
    queryKey: ['leads', q, status],
    queryFn: async () => (await api.get('/leads', { params: { q: q || undefined, status: status || undefined, per_page: 50 } })).data,
  });
  const clientsQ = useQuery({
    queryKey: ['clients'],
    queryFn: async () => (await api.get('/clients', { params: { per_page: 50 } })).data,
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
                { key: 'co', header: 'Company', render: (r) => <Link to={`/leads/${r.id}`} className="font-medium text-sky-700 dark:text-sky-300">{r.company_name}</Link> },
                { key: 'ct', header: 'Contact', render: (r) => <span>{r.contact_name}<br /><span className="text-xs text-[var(--text-muted)]">{r.contact_phone ?? r.contact_email}</span></span> },
                { key: 'sc', header: 'Score', render: (r) => <span title={`Score ${r.score}/100: +20 PH email, +25 valid +63 phone, +status`}><ScoreBar v={r.score} /></span> },
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
                { key: 'n', header: 'Client', render: (r) => <Link to={`/clients/${r.id}`} className="font-medium text-sky-700 dark:text-sky-300">{r.name}</Link> },
                { key: 'i', header: 'Industry', render: (r) => r.industry ?? '—' },
                { key: 'c', header: 'City', render: (r) => r.address_city ?? '—' },
                { key: 's', header: 'Status', render: (r) => <StatusBadge value={r.status} /> },
              ]}
              empty={<EmptyState title="No clients yet" hint="Convert a qualified lead to create your first client profile." action={<button onClick={() => setTab('leads')} className="mt-2 rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Find a lead to convert →</button>} />}
            />
          )}
        </>
      )}

      {showNew && <NewLeadForm onClose={() => setShowNew(false)} onDone={invalidate} />}
      <ConfirmDialog
        open={convertId !== null}
        title="Convert lead to client?"
        body="Quick-convert creates the client profile with the lead's contact as primary. For the full 3-step wizard, open the lead instead."
        onCancel={() => setConvertId(null)}
        onConfirm={() => convertId !== null && convertMut.mutate({ id: convertId, withOpp: true })}
      />
    </div>
  );
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
