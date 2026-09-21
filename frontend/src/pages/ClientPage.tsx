import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { useToast } from '../components/ui/Toaster';
import { ClientJourney, apiErr } from '../components/crm/ClientWidgets';
import { Star } from 'lucide-react';

interface ClientFull {
  id: number;
  name: string;
  industry: string | null;
  address_city: string | null;
  address_province: string | null;
  status: string;
  contact_email: string | null;
  contact_phone: string | null;
  source: string | null;
  created_from_lead_id: number | null;
  contacts?: { id: number; full_name: string; position?: string | null; email: string | null; phone: string | null; is_primary: boolean }[];
}

const SECTIONS = [
  { id: 'profile', label: 'Profile' },
  { id: 'deals', label: 'Deals' },
  { id: 'contracts', label: 'Contracts' },
  { id: 'operations', label: 'Operations' },
  { id: 'conversations', label: 'Conversations' },
  { id: 'billing', label: 'Billing' },
  { id: 'insights', label: 'Insights' },
];

const FULFILLMENT_LABELS: Record<string, string> = {
  none: 'No job orders yet',
  open: 'Open',
  processing: 'Processing',
  partially_fulfilled: 'Partially fulfilled',
  fully_fulfilled: 'Fully fulfilled',
};

interface HeaderContract { id: number; status: string; monthly_billing_centavos: number | null; }
interface HeaderFulfillment { required: number; deployed: number; remaining: number; pct: number | null; status: string; }
interface HeaderSurvey { id: number; response?: { score: number } | null; }

export function ClientPage() {
  const { id = '' } = useParams();

  const detailQ = useQuery({
    queryKey: ['client', Number(id)],
    queryFn: async () => (await api.get(`/clients/${id}`)).data.data as ClientFull,
    enabled: id !== '',
  });
  const cid = detailQ.data?.id ?? 0;

  // Header strip: one number per lifecycle area, each from its owning query.
  const contractsQ = useQuery({
    queryKey: ['contracts', `client-${cid}`],
    queryFn: async () => (await api.get('/contracts', { params: { client_id: cid, per_page: 100 } })).data.data as HeaderContract[],
    enabled: cid > 0,
  });
  const openDealsQ = useQuery({
    queryKey: ['opportunities', `client-${cid}`],
    queryFn: async () => (await api.get('/opportunities', { params: { client_id: cid, per_page: 100 } })).data.data as { id: number; stage: string }[],
    enabled: cid > 0,
  });
  const opsQ = useQuery({
    queryKey: ['client-ops', cid],
    queryFn: async () => (await api.get(`/clients/${cid}/operations`)).data.data as { fulfillment: HeaderFulfillment; billing: { outstanding_centavos?: number } },
    enabled: cid > 0,
  });
  const satQ = useQuery({
    queryKey: ['surveys', `client-${cid}`],
    queryFn: async () => (await api.get('/surveys', { params: { client_id: cid, per_page: 10 } })).data.data as HeaderSurvey[],
    enabled: cid > 0,
  });

  const activeContracts = (contractsQ.data ?? []).filter((c) => c.status === 'active');
  const monthly = activeContracts.reduce((a, c) => a + (c.monthly_billing_centavos ?? 0), 0);
  const openDeals = (openDealsQ.data ?? []).filter((o) => !['won', 'lost'].includes(o.stage)).length;
  const ful = opsQ.data?.fulfillment;
  const latestScore = (satQ.data ?? []).find((s) => s.response)?.response?.score;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-3">
        <Link to="/leads" className="text-sm text-[var(--text-muted)]">← Clients</Link>
      </div>
      {detailQ.isLoading ? (
        <p className="text-sm text-[var(--text-muted)]">Loading client profile…</p>
      ) : detailQ.isError || !detailQ.data ? (
        <div className="card p-6 text-sm">Couldn't load this client. It may have been archived. <button className="text-sky-600 underline" onClick={() => detailQ.refetch()}>Retry</button></div>
      ) : (
        <>
          <div>
            <h1 className="text-xl font-bold">{detailQ.data.name}</h1>
            <p className="mt-1 text-sm text-[var(--text-muted)]">
              <StatusBadge value={detailQ.data.status} /> {detailQ.data.industry ?? '—'} · {[detailQ.data.address_city, detailQ.data.address_province].filter(Boolean).join(', ') || '—'}
            </p>
          </div>
          <div className="grid grid-cols-2 gap-2 md:grid-cols-5">
            <div className="card p-3"><p className="text-xs text-[var(--text-muted)]">Active contracts</p><p className="text-xl font-bold tabular-nums">{contractsQ.isLoading ? '…' : activeContracts.length}</p></div>
            <div className="card p-3"><p className="text-xs text-[var(--text-muted)]">Open deals</p><p className="text-xl font-bold tabular-nums">{openDealsQ.isLoading ? '…' : openDeals}</p></div>
            <div className="card p-3"><p className="text-xs text-[var(--text-muted)]">Fulfillment <span title="Via Client Management">ⓘ</span></p><p className="text-xl font-bold tabular-nums">{opsQ.isLoading ? '…' : ful?.pct !== null && ful?.pct !== undefined ? `${ful.pct}%` : '—'}</p></div>
            <div className="card p-3"><p className="text-xs text-[var(--text-muted)]">Monthly value</p><p className="text-xl font-bold tabular-nums">{contractsQ.isLoading ? '…' : formatPHP(monthly)}</p></div>
            <div className="card p-3"><p className="text-xs text-[var(--text-muted)]">Satisfaction</p><p className="text-xl font-bold tabular-nums">{satQ.isLoading ? '…' : latestScore !== undefined ? `${latestScore}/10` : '—'}</p></div>
          </div>
          <nav aria-label="Page sections" className="flex flex-wrap gap-1.5">
            {SECTIONS.map((s) => (
              <a key={s.id} href={`#${s.id}`}
                className="rounded-lg border border-[var(--border)] px-3 py-1.5 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
                {s.label}
              </a>
            ))}
          </nav>
          <section id="profile" aria-label="Profile" className="flex scroll-mt-24 flex-col gap-3">
            <h2 className="text-base font-semibold">Profile</h2>
            <ProfileStats client={detailQ.data} />
            <h3 className="text-sm font-medium text-[var(--text-muted)]">People to talk to</h3>
            <ContactsTab client={detailQ.data} />
          </section>
          <section id="deals" aria-label="Deals" className="flex scroll-mt-24 flex-col gap-3">
            <h2 className="text-base font-semibold">Deals</h2>
            <p className="text-sm text-[var(--text-muted)]">Every opportunity under this client — past and present. A new requirement never makes them a lead again.</p>
            <OppsTab clientId={detailQ.data.id} />
          </section>
          <section id="contracts" aria-label="Contracts" className="flex scroll-mt-24 flex-col gap-3">
            <h2 className="text-base font-semibold">Contracts</h2>
            <ContractsSection clientId={detailQ.data.id} />
          </section>
          <section id="operations" aria-label="Operations" className="flex scroll-mt-24 flex-col gap-3">
            <h2 className="text-base font-semibold">Operations <span className="text-xs font-normal text-[var(--text-muted)]">via Client Management</span></h2>
            <OperationsSection clientId={detailQ.data.id} />
          </section>
          <section id="conversations" aria-label="Conversations" className="flex scroll-mt-24 flex-col gap-3">
            <h2 className="text-base font-semibold">Conversations</h2>
            <h3 className="text-sm font-medium text-[var(--text-muted)]">Touchpoints</h3>
            <CommsTab clientId={detailQ.data.id} />
            <h3 className="text-sm font-medium text-[var(--text-muted)]">Feedback</h3>
            <SurveysTab clientId={detailQ.data.id} />
            <h3 className="text-sm font-medium text-[var(--text-muted)]">Promised follow-ups</h3>
            <FollowupsTab clientId={detailQ.data.id} />
          </section>
          <section id="billing" aria-label="Billing" className="flex scroll-mt-24 flex-col gap-3">
            <h2 className="text-base font-semibold">Billing <span className="text-xs font-normal text-[var(--text-muted)]">via Finance</span></h2>
            <FinanceTab clientId={detailQ.data.id} />
          </section>
          <section id="insights" aria-label="Insights" className="flex scroll-mt-24 flex-col gap-3">
            <h2 className="text-base font-semibold">Insights</h2>
            <InsightsSection clientId={detailQ.data.id} clientName={detailQ.data.name} />
          </section>
        </>
      )}
    </div>
  );
}

function ProfileStats({ client }: { client: ClientFull }) {
  const oppsQ = useQuery({
    queryKey: ['opportunities', `client-${client.id}`],
    queryFn: async () => (await api.get('/opportunities', { params: { client_id: client.id, per_page: 100 } })).data.data as { id: number; stage: string }[],
  });
  const open = (oppsQ.data ?? []).filter((o) => !['won', 'lost'].includes(o.stage)).length;
  const fupsQ = useQuery({
    queryKey: ['followups', `client-${client.id}`],
    queryFn: async () => (await api.get('/followups', { params: { client_id: client.id, per_page: 100 } })).data.data as { id: number; status: string }[],
  });
  const openFups = (fupsQ.data ?? []).filter((f) => !['done'].includes(f.status)).length;

  return (
    <div className="flex flex-col gap-3">
      <div className="card grid grid-cols-2 gap-3 p-4 text-sm md:grid-cols-4">
        <div><p className="text-xs text-[var(--text-muted)]">Contact</p><p>{client.contact_phone ?? client.contact_email ?? '—'}</p></div>
        <div><p className="text-xs text-[var(--text-muted)]">Source</p><p className="capitalize">{client.source ?? '—'}</p></div>
        <div><p className="text-xs text-[var(--text-muted)]">Open deals</p><p className="font-semibold tabular-nums">{oppsQ.isLoading ? '…' : open}</p></div>
        <div><p className="text-xs text-[var(--text-muted)]">Open follow-ups</p><p className="font-semibold tabular-nums">{fupsQ.isLoading ? '…' : openFups}</p></div>
      </div>
      {client.created_from_lead_id && (
        <p className="text-xs text-[var(--text-muted)]">Converted from lead <Link to={`/leads/${client.created_from_lead_id}`} className="text-sky-600 underline">#{client.created_from_lead_id}</Link>.</p>
      )}
    </div>
  );
}

function ContactsTab({ client }: { client: ClientFull }) {
  const toast = useToast();
  const qc = useQueryClient();
  const [f, setF] = useState({ full_name: '', position: '', email: '', phone: '' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement>) => setF({ ...f, [k]: e.target.value });

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setBusy(true);
    setErr('');
    try {
      await api.post(`/clients/${client.id}/contacts`, {
        full_name: f.full_name, position: f.position || undefined,
        email: f.email || undefined, phone: f.phone || undefined,
      });
      toast('success', 'Contact added.');
      setF({ full_name: '', position: '', email: '', phone: '' });
      qc.invalidateQueries({ queryKey: ['client', client.id] });
    } catch (e) {
      setErr(apiErr(e, 'Could not add contact.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="flex flex-col gap-3">
      <DataTable
        rows={(client.contacts ?? []).map((c) => ({ ...c, id: c.id }))}
        columns={[
          { key: 'n', header: 'Name', render: (r) => <span className="font-medium">{r.full_name} {r.is_primary && <span className="text-xs text-sky-600">(primary)</span>}</span> },
          { key: 'p', header: 'Position', render: (r) => r.position ?? '—' },
          { key: 'e', header: 'Email', render: (r) => r.email ?? '—' },
          { key: 'ph', header: 'Phone', render: (r) => r.phone ?? '—' },
        ]}
        empty={<EmptyState title="No contacts yet" hint="Add the first person you talk to at this account." />}
      />
      <form onSubmit={submit} className="card flex flex-wrap items-end gap-2 p-4">
        <label className="min-w-40 flex-1 text-xs">Full name *<input required value={f.full_name} onChange={set('full_name')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" /></label>
        <label className="min-w-32 flex-1 text-xs">Position<input value={f.position} onChange={set('position')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" /></label>
        <label className="min-w-40 flex-1 text-xs">Email<input type="email" value={f.email} onChange={set('email')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" /></label>
        <label className="min-w-32 flex-1 text-xs">Phone<input value={f.phone} onChange={set('phone')} placeholder="+639…" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" /></label>
        <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Adding…' : 'Add'}</button>
      </form>
      {err && <p className="text-sm text-red-600">{err}</p>}
    </div>
  );
}

function OppsTab({ clientId }: { clientId: number }) {
  const q = useQuery({
    queryKey: ['opportunities', `client-${clientId}`],
    queryFn: async () => (await api.get('/opportunities', { params: { client_id: clientId, per_page: 100 } })).data.data as { id: number; title: string; stage: string; value_centavos: number; probability: number }[],
  });
  if (q.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading deals…</p>;
  return (
    <>
      <DataTable
        rows={q.data ?? []}
        columns={[
          { key: 't', header: 'Deal', render: (r) => <span className="font-medium">{r.title}</span> },
          { key: 's', header: 'Stage', render: (r) => <StatusBadge value={r.stage} /> },
          { key: 'v', header: 'Value', render: (r) => <span className="tabular-nums">{formatPHP(r.value_centavos)} × {r.probability}%</span> },
        ]}
        empty={<EmptyState title="No deals yet" hint="Open the first opportunity for this client." action={<Link to={`/pipeline?client=${clientId}`} className="mt-2 inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">+ New deal</Link>} />}
      />
      <Link to={`/pipeline?client=${clientId}`} className="mt-2 inline-block text-sm text-sky-600 underline">Open deal board with this client preselected →</Link>
    </>
  );
}

function CommsTab({ clientId }: { clientId: number }) {
  const q = useQuery({
    queryKey: ['activities', `client-${clientId}`],
    queryFn: async () => (await api.get('/activities', { params: { client_id: clientId, per_page: 50 } })).data.data as { id: number; type: string; subject: string | null; outcome: string | null; occurred_at: string }[],
  });
  if (q.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading timeline…</p>;
  return (
    <>
      <DataTable
        rows={q.data ?? []}
        columns={[
          { key: 'w', header: 'When', render: (r) => new Date(r.occurred_at).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) },
          { key: 't', header: 'Type', render: (r) => <StatusBadge value={r.type} /> },
          { key: 's', header: 'Subject', render: (r) => r.subject ?? '—' },
          { key: 'o', header: 'Outcome', render: (r) => r.outcome ?? '—' },
        ]}
        empty={<EmptyState title="Nothing logged yet" hint="Log the first touchpoint from Communications." action={<Link to="/comms" className="mt-2 inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Go to Communications</Link>} />}
      />
      <Link to="/comms" className="mt-2 inline-block text-sm text-sky-600 underline">Log activity →</Link>
    </>
  );
}

function SurveysTab({ clientId }: { clientId: number }) {
  const q = useQuery({
    queryKey: ['surveys', `client-${clientId}`],
    queryFn: async () => (await api.get('/surveys', { params: { client_id: clientId, per_page: 50 } })).data.data as { id: number; template_name?: string; status: string; response?: { score: number } | null }[],
  });
  if (q.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading surveys…</p>;
  return (
    <>
      <DataTable
        rows={q.data ?? []}
        columns={[
          { key: 't', header: 'Template', render: (r) => r.template_name ?? `#${r.id}` },
          { key: 's', header: 'Status', render: (r) => <StatusBadge value={r.status} /> },
          { key: 'sc', header: 'Score', render: (r) => (r.response ? <span><Star size={11} className="mr-0.5 inline" />{r.response.score}</span> : '—') },
        ]}
        empty={<EmptyState title="No surveys yet" hint="Send the first NPS check to this client." action={<Link to="/surveys" className="mt-2 inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Go to Surveys</Link>} />}
      />
      <Link to="/surveys" className="mt-2 inline-block text-sm text-sky-600 underline">Send survey →</Link>
    </>
  );
}

function FollowupsTab({ clientId }: { clientId: number }) {
  const q = useQuery({
    queryKey: ['followups', `client-${clientId}`],
    queryFn: async () => (await api.get('/followups', { params: { client_id: clientId, per_page: 50 } })).data.data as { id: number; title: string; status: string; due_at: string }[],
  });
  if (q.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading follow-ups…</p>;
  return (
    <>
      <DataTable
        rows={q.data ?? []}
        columns={[
          { key: 't', header: 'Reminder', render: (r) => <span className="font-medium">{r.title}</span> },
          { key: 's', header: 'Status', render: (r) => <StatusBadge value={r.status} /> },
          { key: 'd', header: 'Due', render: (r) => new Date(r.due_at).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) },
        ]}
        empty={<EmptyState title="No reminders yet" hint="Set the first follow-up for this client." action={<Link to="/followups" className="mt-2 inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Go to Follow-ups</Link>} />}
      />
      <Link to="/followups" className="mt-2 inline-block text-sm text-sky-600 underline">Manage reminders →</Link>
    </>
  );
}

function ContractsSection({ clientId }: { clientId: number }) {
  const q = useQuery({
    queryKey: ['contracts', `client-${clientId}`],
    queryFn: async () => (await api.get('/contracts', { params: { client_id: clientId, per_page: 100 } })).data.data as {
      id: number; ref: string; title: string; headcount: number | null; contract_months: number | null;
      monthly_billing_centavos: number | null; start_date: string | null; status: string;
    }[],
  });
  if (q.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading contracts…</p>;
  return (
    <DataTable
      rows={q.data ?? []}
      columns={[
        { key: 'r', header: 'Contract', render: (r) => <span className="font-medium">{r.ref}</span> },
        { key: 't', header: 'Terms', render: (r) => <span className="tabular-nums">{r.headcount ?? '?'} heads{r.contract_months ? ` × ${r.contract_months} mo` : ''}</span> },
        { key: 'm', header: 'Monthly', render: (r) => <span className="tabular-nums">{r.monthly_billing_centavos ? formatPHP(r.monthly_billing_centavos) : '—'}</span> },
        { key: 's', header: 'From', render: (r) => r.start_date ? new Date(r.start_date).toLocaleDateString('en-PH', { month: 'short', year: 'numeric' }) : '—' },
        { key: 'st', header: 'Status', render: (r) => <StatusBadge value={r.status} /> },
      ]}
      empty={<EmptyState title="No contracts yet" hint="Win a deal and the commercial agreement lands here." />}
    />
  );
}

function OperationsSection({ clientId }: { clientId: number }) {
  const q = useQuery({
    queryKey: ['client-ops', clientId],
    queryFn: async () => (await api.get(`/clients/${clientId}/operations`)).data.data as {
      fulfillment: { required: number; deployed: number; remaining: number; pct: number | null; status: string };
    },
  });
  const f = q.data?.fulfillment;
  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm text-[var(--text-muted)]">Summarized from Client Management — the CRM shows status, they run the deployment.</p>
      {q.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading operations…</p> : !f || f.required === 0 ? (
        <EmptyState title="Nothing deployed yet" hint="Active contracts will show required vs deployed headcount here." />
      ) : (
        <div className="card p-4">
          <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm">
            <span><strong className="tabular-nums">{f.deployed}</strong> of <strong className="tabular-nums">{f.required}</strong> deployed</span>
            <span className="text-[var(--text-muted)]"><strong className="tabular-nums">{f.remaining}</strong> remaining</span>
            <span className="ml-auto"><StatusBadge value={FULFILLMENT_LABELS[f.status] ?? f.status} /></span>
          </div>
          <div className="mt-2 h-2.5 overflow-hidden rounded bg-slate-100 dark:bg-slate-800" role="progressbar" aria-valuenow={f.pct ?? 0} aria-valuemin={0} aria-valuemax={100} aria-label="Fulfillment">
            <div className="h-full rounded bg-sky-500" style={{ width: `${f.pct ?? 0}%` }} />
          </div>
        </div>
      )}
      <ClientJourney clientId={clientId} />
    </div>
  );
}

function InsightsSection({ clientId, clientName }: { clientId: number; clientName: string }) {
  const q = useQuery({
    queryKey: ['client-insights', clientId],
    queryFn: async () => (await api.get(`/insights/clients/${clientId}`)).data.data as {
      level: string; drivers: string[]; nba: { kind: string; title: string; link: string }[]; ai_preview?: boolean;
    },
  });
  if (q.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading insights…</p>;
  if (q.isError || !q.data) return <p className="text-sm text-[var(--text-muted)]">No insights for this client yet.</p>;
  return (
    <div className="card flex flex-col gap-2 p-4">
      <div className="flex items-center gap-2 text-sm">
        <span className="text-[var(--text-muted)]">Relationship health:</span>
        <StatusBadge value={q.data.level} />
        {q.data.ai_preview && <span className="rounded-full bg-violet-100 px-2 py-0.5 text-[11px] text-violet-800 dark:bg-violet-900/40 dark:text-violet-200">AI preview</span>}
      </div>
      <ul className="list-disc pl-5 text-sm">
        {q.data.drivers.map((d, i) => <li key={i}>{d}</li>)}
      </ul>
      {q.data.nba.length > 0 && (
        <div className="mt-1 flex flex-col gap-1.5">
          {q.data.nba.map((a, i) => (
            <Link key={i} to={a.link} className="text-sm text-sky-700 hover:underline dark:text-sky-300">→ {a.title}</Link>
          ))}
        </div>
      )}
      {q.data.nba.length === 0 && <p className="text-sm text-[var(--text-muted)]">{clientName} looks healthy — nothing needs attention.</p>}
    </div>
  );
}

function FinanceTab({ clientId }: { clientId: number }) {
  const opsQ = useQuery({
    queryKey: ['client-ops', clientId],
    queryFn: async () => (await api.get(`/clients/${clientId}/operations`)).data.data as { billing: { outstanding_centavos?: number; status?: string }; mock: boolean },
  });
  const invQ = useQuery({
    queryKey: ['invoices', `client-${clientId}`],
    queryFn: async () => (await api.get('/invoices', { params: { client_id: clientId, per_page: 50 } })).data.data as { id: number; ref: string; title: string; amount_centavos: number; balance_centavos: number; status: string; is_overdue: boolean; due_at: string | null }[],
  });
  return (
    <div className="flex flex-col gap-3">
      <div className="card p-4">
        <h3 className="font-medium">Account finance <span className="text-xs font-normal text-[var(--text-muted)]">(via Finance)</span></h3>
        {opsQ.isLoading ? <p className="mt-1 text-sm">Loading…</p> : (
          <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
            <div className="rounded-lg border border-[var(--border)] p-2">
              <p className="text-xs text-[var(--text-muted)]">AR balance</p>
              <p className="font-semibold tabular-nums">{formatPHP(opsQ.data?.billing.outstanding_centavos ?? 0)}</p>
            </div>
            <div className="rounded-lg border border-[var(--border)] p-2">
              <p className="text-xs text-[var(--text-muted)]">Account standing</p>
              <p className="font-semibold capitalize">{opsQ.data?.billing.status ?? '—'}</p>
            </div>
          </div>
        )}
      </div>
      <div className="card p-4">
        <h3 className="mb-2 font-medium">Invoices</h3>
        {invQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading invoices…</p>
          : (invQ.data ?? []).length === 0 ? <p className="text-sm text-[var(--text-muted)]">No invoices yet — win a deal and the mock draft appears here.</p> : (
            <ul className="flex flex-col gap-2 text-sm">
              {invQ.data!.map((inv) => (
                <li key={inv.id} className="flex flex-wrap items-center gap-2 border-b border-[var(--border)] pb-2 last:border-0">
                  <span className="font-medium">{inv.ref}</span>
                  <StatusBadge value={inv.is_overdue ? 'overdue' : inv.status} />
                  <span className="ml-auto font-semibold tabular-nums">{formatPHP(inv.balance_centavos)} <span className="font-normal text-xs text-[var(--text-muted)]">/ {formatPHP(inv.amount_centavos)}</span></span>
                </li>
              ))}
            </ul>
          )}
        <Link to="/finance" className="mt-2 inline-block text-sm text-sky-600 underline">Open Finance section →</Link>
      </div>
    </div>
  );
}
