import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { OppPrompt } from '../components/crm/OppPrompt';
import { LeadTouchPrompt } from '../components/crm/LeadTouchPrompt';
import { useToast } from '../components/ui/Toaster';
import { apiErr } from '../components/crm/ClientWidgets';

interface LeadFull {
  id: string;
  company_id: string | null;
  owner_id: number;
  owner_name?: string | null;
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

const MANUAL_STATUSES = ['new', 'contacted', 'qualified', 'unqualified'];
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
  const [unqualify, setUnqualify] = useState(false);
  const [oppPrompt, setOppPrompt] = useState(false);
  const [touchPrompt, setTouchPrompt] = useState(false);
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
      setUnqualify(true);
    } else {
      setStatusMut.mutate({ st }, {
        onSuccess: () => {
          if (!leadQ.data?.company_id) return;
          // Contacted ritual mirrors the opportunity one; qualifying opens the deal prompt.
          if (st === 'contacted') setTouchPrompt(true);
          if (st === 'qualified') setOppPrompt(true);
        },
      });
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
                  <StatusBadge value={lead.status} /> {lead.contact_name} · {lead.contact_phone ?? lead.contact_email ?? 'no contact detail'} · <span className="capitalize">{lead.source ?? 'unknown source'}</span>{lead.headcount_needed ? ` · ${lead.headcount_needed} heads${lead.positions ? ` (${lead.positions})` : ''}` : ''}{lead.owner_name ? ` · Owner: ${lead.owner_name}` : ''}
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
                      <select value={lead.status} disabled={['converted', 'unqualified'].includes(lead.status)} onChange={(e) => changeStatus(e.target.value)} className="ml-1 rounded border border-[var(--border)] bg-transparent px-2 py-1 text-sm" aria-label="Lead status">
                        {MANUAL_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                      </select>
                    </label>
                    {lead.company_id && lead.status !== 'unqualified' && (
                      <button onClick={() => setOppPrompt(true)} className="rounded-lg border border-[var(--border)] px-3 py-1.5 text-xs">+ Deal</button>
                    )}
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
              <ConfirmDialog
                open={unqualify}
                title={`Disqualify ${lead.company_name}?`}
                body="They leave the active queue. A reason is required — it stays on the record."
                confirmLabel="Disqualify"
                input={{ label: 'Why is this lead unqualified?', placeholder: 'e.g. No budget this year', required: true }}
                onCancel={() => setUnqualify(false)}
                onConfirm={(reason) => {
                  if (reason) setStatusMut.mutate({ st: 'unqualified', reason });
                  setUnqualify(false);
                }}
              />
              {oppPrompt && leadQ.data?.company_id && (
                <OppPrompt
                  companyId={leadQ.data.company_id}
                  companyName={leadQ.data.company_name}
                  headcount={leadQ.data.headcount_needed}
                  onClose={() => setOppPrompt(false)}
                  onDone={() => { qc.invalidateQueries({ queryKey: ['opportunities'] }); qc.invalidateQueries({ queryKey: ['dashboard'] }); }}
                />
              )}
              {touchPrompt && leadQ.data?.company_id && (
                <LeadTouchPrompt
                  companyId={leadQ.data.company_id}
                  companyName={leadQ.data.company_name}
                  headcount={leadQ.data.headcount_needed}
                  onClose={() => setTouchPrompt(false)}
                  onDone={() => { qc.invalidateQueries({ queryKey: ['followups'] }); }}
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

