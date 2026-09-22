import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { StatusBadge } from '../components/ui/StatusBadge';
import { useToast } from '../components/ui/Toaster';
import { apiErr } from '../components/crm/ClientWidgets';

interface LeadFull {
  id: string;
  company_name: string;
  contact_name: string;
  contact_email: string | null;
  contact_phone: string | null;
  headcount_needed: number | null;
  positions: string | null;
  source: string | null;
  status: string;
  score: number;
  notes: string | null;
  converted_client_id: string | null;
}

const LEAD_STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted'];
const JOURNEY = [
  { key: 'capture', label: 'Captured' },
  { key: 'qualify', label: 'Qualified' },
  { key: 'convert', label: 'Converted' },
];

function journeyState(lead: LeadFull): number {
  if (lead.status === 'converted' || lead.converted_client_id) return 2;
  if (['contacted', 'qualified'].includes(lead.status)) return 1;
  return lead.status === 'new' ? 0 : 1;
}

export function LeadPage() {
  const { id = '' } = useParams();
  const [wizard, setWizard] = useState(false);
  const toast = useToast();
  const qc = useQueryClient();

  const leadQ = useQuery({
    queryKey: ['lead', id],
    queryFn: async () => (await api.get(`/leads/${id}`)).data.data as LeadFull,
    enabled: id !== '',
  });

  const setStatusMut = useMutation({
    mutationFn: async ({ st, reason }: { st: string; reason?: string }) =>
      (await api.put(`/leads/${id}`, { status: st, unqualified_reason: reason })).data,
    onSuccess: () => {
      toast('success', 'Lead status updated.');
      qc.invalidateQueries({ queryKey: ['lead', id] });
      qc.invalidateQueries({ queryKey: ['leads'] });
    },
    onError: (e) => toast('error', apiErr(e, 'Could not update status.')),
  });

  const changeStatus = (st: string) => {
    if (st === 'unqualified') {
      const reason = window.prompt('Why is this lead unqualified? (required)');
      if (!reason) return;
      setStatusMut.mutate({ st, reason });
    } else {
      setStatusMut.mutate({ st });
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <Link to="/leads" className="text-sm text-[var(--text-muted)]">← Leads</Link>
      {leadQ.isLoading ? (
        <p className="text-sm text-[var(--text-muted)]">Loading lead…</p>
      ) : leadQ.isError || !leadQ.data ? (
        <div className="card p-6 text-sm">Couldn't load this lead. <button className="text-sky-600 underline" onClick={() => leadQ.refetch()}>Retry</button></div>
      ) : (
        (() => {
          const lead = leadQ.data;
          const stage = journeyState(lead);
          return (
            <>
              <div>
                <h1 className="text-xl font-bold">{lead.company_name}</h1>
                <p className="mt-1 text-sm text-[var(--text-muted)]">
                  <StatusBadge value={lead.status} /> {lead.contact_name} · {lead.contact_phone ?? lead.contact_email ?? 'no contact detail'} · <span className="capitalize">{lead.source ?? 'unknown source'}</span>{lead.headcount_needed ? ` · ${lead.headcount_needed} heads${lead.positions ? ` (${lead.positions})` : ''}` : ''}
                </p>
              </div>

              <div className="card p-4">
                <h2 className="font-semibold">Qualification journey</h2>
                <div className="mt-2 flex items-center gap-1" aria-label="Lead journey progress">
                  {JOURNEY.map((s, i) => (
                    <span key={s.key} title={s.label} className={`h-2 flex-1 rounded ${i <= stage ? 'bg-sky-500' : 'bg-slate-200 dark:bg-slate-700'}`} />
                  ))}
                </div>
                <div className="mt-1 flex justify-between text-xs text-[var(--text-muted)]">
                  {JOURNEY.map((s) => <span key={s.key}>{s.label}</span>)}
                </div>
                {lead.converted_client_id ? (
                  <p className="mt-2 text-sm">Converted → <Link to={`/clients/${lead.converted_client_id}`} className="text-sky-600 underline">open client profile</Link>.</p>
                ) : (
                  <div className="mt-3 flex flex-wrap items-center gap-2">
                    <label className="text-xs text-[var(--text-muted)]">Move to
                      <select value={lead.status} disabled={lead.status === 'converted'} onChange={(e) => changeStatus(e.target.value)} className="ml-1 rounded border border-[var(--border)] bg-transparent px-2 py-1 text-sm" aria-label="Lead status">
                        {LEAD_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                      </select>
                    </label>
                    <button onClick={() => setWizard(true)} className="rounded-lg bg-sky-600 px-3 py-1.5 text-xs text-white">Convert to client →</button>
                  </div>
                )}
              </div>

              <div className="card p-4">
                <h2 className="font-semibold">Score: {lead.score}/100</h2>
                <ScoreExplainer lead={lead} />
              </div>

              {lead.notes && (
                <div className="card p-4">
                  <h2 className="font-semibold">Notes</h2>
                  <p className="mt-1 whitespace-pre-wrap text-sm">{lead.notes}</p>
                </div>
              )}

              <DuplicatePanel lead={lead} />
              {wizard && (
                <ConvertWizard
                  lead={lead}
                  onClose={() => setWizard(false)}
                  onDone={(clientId) => {
                    setWizard(false);
                    qc.invalidateQueries({ queryKey: ['lead', id] });
                    qc.invalidateQueries({ queryKey: ['leads'] });
                    window.location.assign(`/clients/${clientId}`);
                  }}
                />
              )}
            </>
          );
        })()
      )}
    </div>
  );
}

function ScoreExplainer({ lead }: { lead: LeadFull }) {
  const rows: [string, boolean][] = [
    ['PH corporate email (+20)', !!lead.contact_email && lead.contact_email.endsWith('.ph')],
    ['Valid +63 mobile (+25)', !!lead.contact_phone && /^\+63\d{10}$/.test(lead.contact_phone)],
    ['Worked status: contacted (+15) / qualified (+30)', ['contacted', 'qualified', 'converted'].includes(lead.status)],
    ['Notes on file (+10)', !!lead.notes],
    ['Headcount need stated (+10)', (lead.headcount_needed ?? 0) > 0],
  ];
  return (
    <ul className="mt-2 flex flex-col gap-1 text-sm">
      {rows.map(([label, hit]) => (
        <li key={label} className="flex items-center gap-2">
          <span className={hit ? 'text-green-600' : 'text-slate-300'}>{hit ? '●' : '○'}</span>
          <span className={hit ? '' : 'text-[var(--text-muted)]'}>{label}</span>
        </li>
      ))}
    </ul>
  );
}

function DuplicatePanel({ lead }: { lead: LeadFull }) {
  const emailQ = useQuery({
    queryKey: ['leads', 'dup-email', lead.id],
    queryFn: async () => (await api.get('/leads', { params: { q: lead.contact_email ?? undefined, per_page: 10 } })).data.data as { id: string; company_name: string; contact_email: string | null }[],
    enabled: !!lead.contact_email,
  });
  const phoneQ = useQuery({
    queryKey: ['leads', 'dup-phone', lead.id],
    queryFn: async () => (await api.get('/leads', { params: { q: lead.contact_phone ?? undefined, per_page: 10 } })).data.data as { id: string; company_name: string; contact_phone: string | null }[],
    enabled: !!lead.contact_phone,
  });
  const dups = [...(emailQ.data ?? []), ...(phoneQ.data ?? [])]
    .filter((r, i, arr) => r.id !== lead.id && arr.findIndex((x) => x.id === r.id) === i);
  if (!lead.contact_email && !lead.contact_phone) return null;
  if ((emailQ.isLoading || phoneQ.isLoading) && dups.length === 0) return null;
  if (dups.length === 0) {
    return (
      <div className="card border-l-4 border-l-green-500 p-4">
        <p className="text-sm"><strong>No duplicates found</strong> — this contact appears unique.</p>
      </div>
    );
  }
  return (
    <div className="card border-l-4 border-l-amber-500 p-4">
      <h2 className="font-semibold">Possible duplicates ({dups.length})</h2>
      <ul className="mt-1 flex flex-col gap-1 text-sm">
        {dups.map((d) => (
          <li key={d.id}><Link to={`/leads/${d.id}`} className="text-sky-600 underline">{d.company_name}</Link></li>
        ))}
      </ul>
    </div>
  );
}

function ConvertWizard({ lead, onClose, onDone }: { lead: LeadFull; onClose: () => void; onDone: (clientId: string) => void }) {
  const toast = useToast();
  const [step, setStep] = useState(0);
  const [withOpp, setWithOpp] = useState(true);
  const [oppTitle, setOppTitle] = useState(`${lead.company_name} — staffing`);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');

  const submit = async () => {
    setBusy(true);
    setErr('');
    try {
      const r = await api.post(`/leads/${lead.id}/convert`, withOpp ? { create_opportunity: true, opportunity_title: oppTitle || undefined } : {});
      toast('success', 'Converted — client profile created.');
      onDone(r.data.data.client_id);
    } catch (e) {
      setErr(apiErr(e, 'Conversion failed. Maybe already converted?'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Convert to client ({step + 1}/3)</h2>
        <div className="mt-2 flex gap-1">{[0, 1, 2].map((s) => <span key={s} className={`h-1.5 flex-1 rounded ${s <= step ? 'bg-sky-500' : 'bg-slate-200 dark:bg-slate-700'}`} />)}</div>

        {step === 0 && (
          <div className="mt-3 text-sm">
            <p className="text-[var(--text-muted)]">Confirm the company record:</p>
            <p className="mt-1 font-semibold">{lead.company_name}</p>
            <p className="text-xs text-[var(--text-muted)]">{lead.contact_name} · {lead.contact_phone ?? lead.contact_email}</p>
          </div>
        )}
        {step === 1 && (
          <div className="mt-3 text-sm">
            <p className="text-[var(--text-muted)]">Primary contact carried over:</p>
            <p className="mt-1 font-semibold">{lead.contact_name}</p>
            <p className="text-xs text-[var(--text-muted)]">{lead.contact_phone ?? '—'} · {lead.contact_email ?? '—'}</p>
          </div>
        )}
        {step === 2 && (
          <div className="mt-3 text-sm">
            <label className="flex items-center gap-2">
              <input type="checkbox" checked={withOpp} onChange={(e) => setWithOpp(e.target.checked)} /> Open an opportunity too
            </label>
            {withOpp && (
              <input value={oppTitle} onChange={(e) => setOppTitle(e.target.value)} placeholder="Opportunity title" className="mt-2 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" />
            )}
          </div>
        )}

        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-between">
          <div className="flex gap-2">
            <button onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
            {step > 0 && <button onClick={() => setStep(step - 1)} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">← Back</button>}
          </div>
          {step < 2
            ? <button onClick={() => setStep(step + 1)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Next →</button>
            : <button disabled={busy} onClick={submit} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Converting…' : 'Convert'}</button>}
        </div>
      </div>
    </div>
  );
}
