import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { KpiCard } from '../components/ui/KpiCard';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { OppPrompt } from '../components/crm/OppPrompt';
import { Download, Upload } from 'lucide-react';
import { useToast } from '../components/ui/Toaster';
import { useSettingsList } from '../hooks/useSettings';
import { apiErr } from '../components/crm/ClientWidgets';

interface Lead {
  id: string;
  company_id: string | null;
  company_name: string;
  contact_name: string;
  contact_email: string | null;
  contact_phone: string | null;
  headcount_needed: number | null;
  source: string | null;
  status: string;
  score: number;
}

const LEAD_STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted'];
const MANUAL_STATUSES = ['new', 'contacted', 'qualified', 'unqualified'];

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
  const [unqualify, setUnqualify] = useState<Lead | null>(null);
  const [oppPrompt, setOppPrompt] = useState<Lead | null>(null);
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
    mutationFn: async ({ id, st, reason }: { id: string; st: string; reason?: string }) =>
      (await api.put(`/leads/${id}`, { status: st, unqualified_reason: reason })).data,
    onSuccess: () => {
      toast('success', 'Lead status updated.');
      invalidate();
    },
    onError: (e: unknown) => toast('error', apiErr(e, 'Could not update status.')),
  });

  const changeStatus = (r: Lead, st: string) => {
    if (st === 'unqualified') {
      setUnqualify(r);
    } else {
      setStatusMut.mutate({ id: r.id, st });
      // Qualifying opens the deal prompt — clients are born from won deals, never by hand.
      if (st === 'qualified' && r.company_id) setOppPrompt(r);
    }
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
          onDeal={setOppPrompt}
          empty={needsOnly
            ? <EmptyState title="All caught up" hint="Nothing waiting for a first response. New inquiries will land here." />
            : <EmptyState title="No leads yet" hint="Capture your first lead — company, contact and a +63 mobile is enough." action={<button onClick={() => setShowNew(true)} className="mt-2 rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">+ New lead</button>} />}
        />
      )}

      {showNew && <NewLeadForm onClose={() => setShowNew(false)} onDone={invalidate} />}
      {showImport && <ImportModal onClose={() => setShowImport(false)} onDone={invalidate} />}
      {oppPrompt?.company_id && (
        <OppPrompt
          companyId={oppPrompt.company_id}
          companyName={oppPrompt.company_name}
          headcount={oppPrompt.headcount_needed}
          onClose={() => setOppPrompt(null)}
          onDone={() => { invalidate(); qc.invalidateQueries({ queryKey: ['opportunities'] }); }}
        />
      )}
      <ConfirmDialog
        open={unqualify !== null}
        title={`Disqualify ${unqualify?.company_name ?? 'lead'}?`}
        body="They leave the active queue. A reason is required — it stays on the record."
        confirmLabel="Disqualify"
        input={{ label: 'Why is this lead unqualified?', placeholder: 'e.g. No budget this year', required: true }}
        onCancel={() => setUnqualify(null)}
        onConfirm={(reason) => {
          if (unqualify && reason) setStatusMut.mutate({ id: unqualify.id, st: 'unqualified', reason });
          setUnqualify(null);
        }}
      />
    </div>
  );
}

function LeadTable({ rows, onStatus, onDeal, empty }: {
  rows: Lead[];
  onStatus: (r: Lead, st: string) => void;
  onDeal: (r: Lead) => void;
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
          <select value={r.status} disabled={['converted', 'unqualified'].includes(r.status)} onChange={(e) => onStatus(r, e.target.value)}
            className="rounded border border-[var(--border)] bg-transparent px-1 py-0.5 text-xs" aria-label={`Status of ${r.company_name}`}>
            {MANUAL_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        ) },
        { key: 'ac', header: 'Actions', render: (r) => r.status === 'converted'
          ? <StatusBadge value="converted" />
          : r.status === 'unqualified'
            ? <span className="text-xs text-[var(--text-muted)]">closed</span>
            : r.company_id
              ? <button onClick={() => onDeal(r)} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs">+ Deal</button>
              : <span className="text-xs text-[var(--text-muted)]">—</span> },
      ]}
      empty={empty}
    />
  );
}

interface LookupCompany { id: string; name: string; address_city: string | null; open_leads_count?: number }

function NewLeadForm({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const sources = useSettingsList('lead_sources', ['referral', 'walk_in', 'website', 'facebook', 'cold_call', 'event']);
  const industries = useSettingsList('industries', ['BPO', 'Manufacturing', 'Hospitality', 'Retail', 'Healthcare', 'Logistics']);
  const [step, setStep] = useState(0);
  const [companyName, setCompanyName] = useState('');
  const [useExisting, setUseExisting] = useState<LookupCompany | null>(null);
  const [co, setCo] = useState({ industry: '', city: '', province: '', email: '', phone: '' });
  const [f, setF] = useState({ contact_name: '', contact_position: '', contact_email: '', contact_phone: '', headcount: '', positions: '', source: 'facebook' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });
  const setC = (k: keyof typeof co) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => { setCo({ ...co, [k]: e.target.value }); setUseExisting(null); };

  const lookupQ = useQuery({
    queryKey: ['companies-lookup', companyName],
    queryFn: async () => (await api.get('/companies/lookup', { params: { name: companyName } })).data.data as LookupCompany[],
    enabled: companyName.trim().length >= 2 && !useExisting,
  });
  const matches = lookupQ.data ?? [];

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!/^\+63\d{10}$/.test(f.contact_phone) && f.contact_phone) { setErr('Phone must be +639XXXXXXXXX.'); return; }
    setBusy(true);
    setErr('');
    try {
      const r = await api.post('/leads', {
        ...(useExisting ? { company_id: useExisting.id } : {
          company: {
            name: companyName.trim(),
            industry: co.industry || undefined,
            address_city: co.city || undefined,
            address_province: co.province || undefined,
            contact_email: co.email || undefined,
            contact_phone: co.phone || undefined,
          },
        }),
        contact_name: f.contact_name,
        contact_position: f.contact_position || undefined,
        contact_email: f.contact_email || undefined,
        contact_phone: f.contact_phone || undefined,
        headcount_needed: f.headcount ? Number(f.headcount) : undefined,
        positions: f.positions || undefined,
        source: f.source,
      });
      const dup = r.data.meta?.duplicate_warning;
      toast('success', dup ? `Lead created — heads up: possible duplicate ${dup.type} #${dup.id}.` : 'Lead created — qualify it next.');
      onDone();
      onClose();
    } catch (e) {
      const status = (e as { response?: { status?: number } })?.response?.status;
      if (status === 409) {
        const existing = (e as { response?: { data?: { meta?: { existing_lead_id?: string } } } })?.response?.data?.meta?.existing_lead_id;
        setErr(`This company already has an open lead.${existing ? ' Open it instead — use the link below.' : ''}`);
        if (existing) toast('info', { title: 'Company already has an open lead.', action: { label: 'Open lead', href: `/leads/${existing}` } });
      } else {
        setErr(apiErr(e, 'Could not create lead.'));
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card my-8 w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">New lead</h2>
        <ol className="mt-2 flex items-center gap-1 text-xs" aria-label="Progress">
          {['Company', 'Contact & need'].map((s, i) => (
            <li key={s} className="flex items-center gap-1">
              <span className={`flex h-5 w-5 items-center justify-center rounded-full font-semibold ${i <= step ? 'bg-sky-600 text-white' : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'}`}>{i + 1}</span>
              <span className={i === step ? 'font-semibold' : 'text-[var(--text-muted)]'}>{s}</span>
              {i === 0 && <span className="mx-1 h-px w-6 bg-[var(--border)]" />}
            </li>
          ))}
        </ol>
        {step === 0 && (
          <div className="mt-3 flex flex-col gap-2 text-sm">
            <p className="text-xs text-[var(--text-muted)]">Which company is asking for manpower? Pick an existing one or describe a new one.</p>
            <label>Company *<input required value={companyName} onChange={(e) => { setCompanyName(e.target.value); setUseExisting(null); }} placeholder="e.g. ABC Manufacturing" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            {useExisting ? (
              <p className="rounded-lg bg-sky-50 px-3 py-2 text-sm dark:bg-sky-900/30">
                Using existing company <strong>{useExisting.name}</strong>
                {useExisting.open_leads_count ? ` (${useExisting.open_leads_count} open lead — creating another is blocked).` : '.'}{' '}
                <button type="button" onClick={() => setUseExisting(null)} className="text-sky-700 underline dark:text-sky-300">Change</button>
              </p>
            ) : matches.length > 0 && (
              <div className="rounded-lg border border-[var(--border)] p-2">
                <p className="mb-1 text-xs text-[var(--text-muted)]">Already in the system?</p>
                {matches.map((m) => (
                  <button key={m.id} type="button" onClick={() => setUseExisting(m)} className="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span className="font-medium">{m.name} <span className="font-normal text-xs text-[var(--text-muted)]">{m.address_city ?? ''}</span></span>
                    <span className="text-xs text-sky-700 dark:text-sky-300">Use →</span>
                  </button>
                ))}
              </div>
            )}
            {!useExisting && (
              <>
                <div className="grid grid-cols-2 gap-2">
                  <label>Industry<select value={co.industry} onChange={setC('industry')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
                    <option value="">—</option>
                    {industries.map((s) => <option key={s} value={s}>{s}</option>)}
                  </select></label>
                  <label>City<input value={co.city} onChange={setC('city')} placeholder="Calamba" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <label>Province<input value={co.province} onChange={setC('province')} placeholder="Laguna" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
                  <label>Company phone<input value={co.phone} onChange={setC('phone')} placeholder="+639…" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
                </div>
                <label>Company email<input type="email" value={co.email} onChange={setC('email')} placeholder="info@company.ph" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
              </>
            )}
          </div>
        )}
        {step === 1 && (
          <div className="mt-3 flex flex-col gap-2 text-sm">
            <p className="text-xs text-[var(--text-muted)]">Who asked, and what do they need? Score is computed automatically.</p>
            <div className="grid grid-cols-2 gap-2">
              <label>Contact person *<input required value={f.contact_name} onChange={set('contact_name')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
              <label>Position<input value={f.contact_position} onChange={set('contact_position')} placeholder="HR Manager" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <label>Email<input type="email" value={f.contact_email} onChange={set('contact_email')} placeholder="hrd@company.ph (+20 score)" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
              <label>Mobile<input value={f.contact_phone} onChange={set('contact_phone')} placeholder="+639XXXXXXXXX (+25 score)" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <label>Heads needed<input value={f.headcount} onChange={set('headcount')} inputMode="numeric" placeholder="40 (+10 score)" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
              <label>Positions<input value={f.positions} onChange={set('positions')} placeholder="e.g. Guards, Janitors" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            </div>
            <label>Source<select value={f.source} onChange={set('source')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
              {sources.map((s) => <option key={s} value={s}>{s}</option>)}
            </select></label>
          </div>
        )}
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-between gap-2">
          <div className="flex gap-2">
            <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
            {step > 0 && <button type="button" onClick={() => { setErr(''); setStep(0); }} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">← Back</button>}
          </div>
          {step === 0
            ? <button type="button" disabled={!companyName.trim()} onClick={() => setStep(1)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">Next →</button>
            : <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Saving…' : 'Create lead'}</button>}
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
