import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { KpiCard } from '../components/ui/KpiCard';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { Download, Upload } from 'lucide-react';
import { useToast } from '../components/ui/Toaster';
import { useSettingsList } from '../hooks/useSettings';
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
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const [needsOnly, setNeedsOnly] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [showImport, setShowImport] = useState(false);
  const [convertId, setConvertId] = useState<number | null>(null);
  const toast = useToast();
  const qc = useQueryClient();

  const leadsQ = useQuery({
    queryKey: ['leads', q, status],
    queryFn: async () => (await api.get('/leads', { params: { q: q || undefined, status: status || undefined, per_page: 50 } })).data,
  });
  // Unfiltered totals for the KPI strip (queues are small; same cap as the table).
  const totalsQ = useQuery({
    queryKey: ['leads', 'totals'],
    queryFn: async () => (await api.get('/leads', { params: { per_page: 100 } })).data.data as Lead[],
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['leads'] });
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
    },
    onError: (e: unknown) => toast('error', apiErr(e, 'Conversion failed. Maybe already converted?')),
  });

  const changeStatus = (r: Lead, st: string) => {
    if (st === 'unqualified') {
      const reason = window.prompt('Why is this lead unqualified? (required)');
      if (!reason) return;
      setStatusMut.mutate({ id: r.id, st, reason });
    } else setStatusMut.mutate({ id: r.id, st });
  };

  const all: Lead[] = leadsQ.data?.data ?? [];
  const rows = (needsOnly ? all.filter((l) => ['new', 'contacted'].includes(l.status)).sort((a, b) => b.score - a.score) : all);
  const totals: Lead[] = totalsQ.data ?? [];
  const waiting = totals.filter((l) => ['new', 'contacted'].includes(l.status));
  const hot = totals.filter((l) => !['converted', 'unqualified'].includes(l.status) && l.score >= 70);
  const won = totals.filter((l) => l.status === 'converted');

  const toggleNeeds = () => {
    if (!needsOnly) setStatus('');
    setNeedsOnly((v) => !v);
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-xl font-bold">Leads</h1>
          <p className="text-sm text-[var(--text-muted)]">Work the queue first, then browse everyone. Capture in under a minute.</p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => setShowImport(true)} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm font-semibold">
            <span className="inline-flex items-center gap-1.5"><Upload size={14} /> Import CSV</span>
          </button>
          <button onClick={() => setShowNew(true)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">
            + New lead
          </button>
        </div>
      </div>
      <div className="flex flex-wrap gap-1.5" role="group" aria-label="Quick filter">
        <button onClick={toggleNeeds} aria-pressed={needsOnly}
          className={`rounded-lg px-3 py-1.5 text-sm font-medium ${needsOnly ? 'bg-sky-600 text-white' : 'border border-[var(--border)]'}`}>
          Needs a response{waiting.length > 0 ? ` (${waiting.length})` : ''}
        </button>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <KpiCard label="Waiting on you" value={totalsQ.isLoading ? '…' : String(waiting.length)} sub="new + contacted — work these first" />
        <KpiCard label="Hot leads" value={totalsQ.isLoading ? '…' : String(hot.length)} sub="open leads scoring 70+" />
        <KpiCard label="Won over" value={totalsQ.isLoading ? '…' : String(won.length)} sub="converted to clients" />
      </div>

      <div className="flex gap-2">
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search company, contact, email…" className="card flex-1 px-3 py-2 text-sm outline-none" />
        <select value={status} onChange={(e) => { setStatus(e.target.value); setNeedsOnly(false); }} className="card px-3 py-2 text-sm" aria-label="Filter by status">
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
        <LeadTable
          rows={rows}
          onStatus={changeStatus}
          onConvert={setConvertId}
          empty={needsOnly
            ? <EmptyState title="All caught up" hint="Nothing waiting for a first response. New inquiries will land here." />
            : <EmptyState title="No leads yet" hint="Capture your first lead — company, contact and a +63 mobile is enough." action={<button onClick={() => setShowNew(true)} className="mt-2 rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">+ New lead</button>} />}
        />
      )}

      {showNew && <NewLeadForm onClose={() => setShowNew(false)} onDone={invalidate} />}
      {showImport && <ImportModal onClose={() => setShowImport(false)} onDone={invalidate} />}
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

function LeadTable({ rows, onStatus, onConvert, empty }: {
  rows: Lead[];
  onStatus: (r: Lead, st: string) => void;
  onConvert: (id: number) => void;
  empty: React.ReactNode;
}) {
  return (
    <DataTable<Lead>
      rows={rows}
      columns={[
        { key: 'co', header: 'Company', render: (r) => <Link to={`/leads/${r.id}`} className="font-medium text-sky-700 dark:text-sky-300">{r.company_name}</Link> },
        { key: 'ct', header: 'Contact', render: (r) => <span>{r.contact_name}<br /><span className="text-xs text-[var(--text-muted)]">{r.contact_phone ?? r.contact_email}</span></span> },
        { key: 'sc', header: 'Score', render: (r) => <span title={`Score ${r.score}/100: +20 PH email, +25 valid +63 phone, +status`}><ScoreBar v={r.score} /></span> },
        { key: 'st', header: 'Status', render: (r) => (
          <select value={r.status} disabled={r.status === 'converted'} onChange={(e) => onStatus(r, e.target.value)}
            className="rounded border border-[var(--border)] bg-transparent px-1 py-0.5 text-xs" aria-label={`Status of ${r.company_name}`}>
            {LEAD_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        ) },
        { key: 'ac', header: 'Actions', render: (r) => r.status === 'converted'
          ? <StatusBadge value="converted" />
          : <button onClick={() => onConvert(r.id)} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs">Convert →</button> },
      ]}
      empty={empty}
    />
  );
}

function NewLeadForm({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const sources = useSettingsList('lead_sources', ['referral', 'walk_in', 'website', 'facebook', 'cold_call', 'event']);
  const [f, setF] = useState({ company_name: '', contact_name: '', contact_email: '', contact_phone: '', headcount: '', positions: '', source: 'facebook' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!/^\+63\d{10}$/.test(f.contact_phone) && f.contact_phone) { setErr('Phone must be +639XXXXXXXXX.'); return; }
    setBusy(true);
    setErr('');
    try {
      const r = await api.post('/leads', {
        ...f,
        contact_email: f.contact_email || undefined,
        contact_phone: f.contact_phone || undefined,
        headcount_needed: f.headcount ? Number(f.headcount) : undefined,
        positions: f.positions || undefined,
      });
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
          <div className="grid grid-cols-2 gap-2">
            <label>Heads needed<input value={f.headcount} onChange={set('headcount')} inputMode="numeric" placeholder="40 (+10 score)" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Positions<input value={f.positions} onChange={set('positions')} placeholder="e.g. Guards, Janitors" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
          <label>Source<select value={f.source} onChange={set('source')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            {sources.map((s) => <option key={s} value={s}>{s}</option>)}
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

function ImportModal({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<{ imported: number; failed: { row: number; errors: string[] }[] } | null>(null);
  const [err, setErr] = useState('');

  const downloadTemplate = async () => {
    try {
      const r = await api.get('/leads/import-template', { responseType: 'blob' });
      const url = URL.createObjectURL(new Blob([r.data], { type: 'text/csv' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = 'leads-template.csv';
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      toast('error', 'Could not download the template.');
    }
  };

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!file) {
      setErr('Pick a CSV file first.');
      return;
    }
    setBusy(true);
    setErr('');
    try {
      const fd = new FormData();
      fd.append('file', file);
      const r = await api.post('/leads/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      setResult(r.data.data);
      toast('success', r.data.message ?? 'Import finished.');
      onDone();
    } catch (e) {
      setErr(apiErr(e, 'Import failed.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Import leads (CSV)</h2>
        <p className="mb-2 text-xs text-[var(--text-muted)]">
          Columns: company_name, contact_name, contact_email, contact_phone, source, notes. Max 500 rows —
          bad rows are reported, good rows still import.
        </p>
        <button type="button" onClick={downloadTemplate} className="text-xs text-sky-600 underline"><span className="inline-flex items-center gap-1"><Download size={12} /> Download template</span></button>
        <input type="file" accept=".csv,.txt" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="mt-3 w-full text-sm" />
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        {result && (
          <div className="mt-3 rounded-lg border border-[var(--border)] p-3 text-sm">
            <p><strong>{result.imported}</strong> imported, <strong>{result.failed.length}</strong> failed.</p>
            {result.failed.length > 0 && (
              <ul className="mt-1 max-h-32 overflow-y-auto text-xs">
                {result.failed.map((f) => (
                  <li key={f.row}>Row {f.row}: {f.errors.join('; ')}</li>
                ))}
              </ul>
            )}
          </div>
        )}
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">{result ? 'Done' : 'Cancel'}</button>
          {!result && <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Importing…' : 'Import'}</button>}
        </div>
      </form>
    </div>
  );
}
