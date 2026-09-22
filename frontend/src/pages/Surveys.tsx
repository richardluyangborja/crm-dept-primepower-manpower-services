import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { KpiCard } from '../components/ui/KpiCard';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { useToast } from '../components/ui/Toaster';
import { hasRole, useSession } from '../store/session';
import { Star } from 'lucide-react';

interface Template {
  id: number;
  name: string;
  type: 'nps' | 'csat' | 'custom';
  questions: { q: string; scale?: number; options?: string[] }[];
  is_active: boolean;
}
interface Survey {
  id: string;
  template_name?: string;
  template_type?: string;
  client_id: string;
  client_name?: string;
  channel: string;
  token: string;
  share_url: string;
  due_at: string | null;
  status: string;
  response?: { score: number; comment: string | null; responded_at: string } | null;
}
interface ClientMini {
  id: string;
  name: string;
}

function apiErr(e: unknown, fallback: string): string {
  if (typeof e === 'object' && e !== null && 'response' in e) {
    const r = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
    if (r?.data?.errors) return Object.values(r.data.errors).flat().join(' ');
    if (r?.data?.message) return r.data.message;
  }
  return fallback;
}

export function SurveysPage() {
  const [tab, setTab] = useState<'surveys' | 'templates' | 'analytics'>('surveys');

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Satisfaction & Surveys</h1>
        <p className="text-sm text-[var(--text-muted)]">Send NPS/CSAT surveys, track responses, act on low scores.</p>
      </div>
      <div className="flex gap-2">
        {(['surveys', 'templates', 'analytics'] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)}
            className={`rounded-lg px-4 py-1.5 text-sm font-medium capitalize ${tab === t ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100' : 'border border-[var(--border)]'}`}>
            {t}
          </button>
        ))}
      </div>
      {tab === 'surveys' && <SurveysInbox />}
      {tab === 'templates' && <TemplatesTab />}
      {tab === 'analytics' && <AnalyticsTab />}
    </div>
  );
}

/* ------------------------------- Surveys inbox ------------------------------ */

function SurveysInbox() {
  const [status, setStatus] = useState('');
  const [showSend, setShowSend] = useState(false);
  const [confirmClient, setConfirmClient] = useState<{ name: string; run: () => void } | null>(null);
  const toast = useToast();
  const qc = useQueryClient();

  const surveysQ = useQuery({
    queryKey: ['surveys', status],
    queryFn: async () =>
      (await api.get('/surveys', { params: { status: status || undefined, per_page: 50 } })).data.data as Survey[],
  });
  const rows = surveysQ.data ?? [];

  const copyLink = async (s: Survey) => {
    const url = `${window.location.origin}/s/${s.token}`;
    try {
      await navigator.clipboard.writeText(url);
      toast('success', 'Share link copied!');
    } catch {
      toast('info', `Copy this link: ${url}`);
    }
  };

  const resendMut = useMutation({
    mutationFn: async (s: Survey) =>
      (await api.post('/surveys', { template_id: (s as unknown as { template_id: number }).template_id, client_id: s.client_id })).data,
    onSuccess: () => {
      toast('success', 'Survey re-sent — new share link ready.');
      qc.invalidateQueries({ queryKey: ['surveys'] });
    },
    onError: (e) => toast('error', apiErr(e, 'Could not re-send.')),
  });

  return (
    <>
      <div className="flex gap-2">
        <select value={status} onChange={(e) => setStatus(e.target.value)} className="card px-3 py-2 text-sm" aria-label="Filter by status">
          <option value="">All statuses</option>
          {['sent', 'responded', 'expired', 'draft'].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <span className="flex-1" />
        <button onClick={() => setShowSend(true)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">+ Send survey</button>
      </div>
      {surveysQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading surveys…</p>
        : surveysQ.isError ? <div className="card p-6 text-sm">Couldn't load surveys. <button className="text-sky-600 underline" onClick={() => surveysQ.refetch()}>Retry</button></div>
        : (
          <DataTable<Survey>
            rows={rows}
            columns={[
              { key: 'c', header: 'Client', render: (r) => r.client_name ?? `#${r.client_id}` },
              { key: 't', header: 'Template', render: (r) => <span>{r.template_name} <span className="text-xs text-[var(--text-muted)]">({r.template_type})</span></span> },
              { key: 's', header: 'Status', render: (r) => <StatusBadge value={r.status} /> },
              { key: 'd', header: 'Due', render: (r) => (r.due_at ? new Date(r.due_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) : '—') },
              {
                key: 'a', header: 'Actions', render: (r) => (
                  <span className="flex gap-1">
                    <button onClick={() => copyLink(r)} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">Copy link</button>
                    {r.response && <span className="rounded bg-green-100 px-2 py-0.5 text-xs text-green-800"><Star size={11} className="mr-0.5 inline" />{r.response.score}</span>}
                    {r.status === 'expired' && <button onClick={() => setConfirmClient({ name: r.client_name ?? 'this client', run: () => resendMut.mutate(r) })} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">Resend</button>}
                  </span>
                ),
              },
            ]}
            empty={<EmptyState title="No surveys sent yet" hint="Pick a template and a client — the share link is ready instantly (mock send, no real email)." action={<button onClick={() => setShowSend(true)} className="mt-2 rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">+ Send survey</button>} />}
          />
        )}
      {showSend && <SendSurveyForm onClose={() => setShowSend(false)} onDone={() => qc.invalidateQueries({ queryKey: ['surveys'] })} />}
      <ConfirmDialog
        open={confirmClient !== null}
        title="Re-send survey?"
        body={`Send a fresh survey link to ${confirmClient?.name ?? ''}? The old expired link stays dead.`}
        onCancel={() => setConfirmClient(null)}
        onConfirm={() => {
          confirmClient?.run();
          setConfirmClient(null);
        }}
      />
    </>
  );
}

function SendSurveyForm({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const [f, setF] = useState({ template_id: '', client_id: '', channel: 'link' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const [confirmSend, setConfirmSend] = useState(false);
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });

  const templatesQ = useQuery({
    queryKey: ['survey-templates'],
    queryFn: async () => (await api.get('/survey-templates')).data.data as Template[],
  });
  const clientsQ = useQuery({
    queryKey: ['clients-mini'],
    queryFn: async () => (await api.get('/clients', { params: { per_page: 100 } })).data.data as ClientMini[],
  });
  const chosenTemplate = templatesQ.data?.find((t) => String(t.id) === f.template_id);
  const chosenClient = clientsQ.data?.find((c) => String(c.id) === f.client_id);

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setConfirmSend(true);
  };

  const doSend = async () => {
    setConfirmSend(false);
    setBusy(true);
    setErr('');
    try {
      const r = await api.post('/surveys', { template_id: Number(f.template_id), client_id: f.client_id, channel: f.channel });
      const url = `${window.location.origin}${r.data.data.share_url}`;
      try {
        await navigator.clipboard.writeText(url);
        toast('success', 'Survey sent — share link copied!');
      } catch {
        toast('success', `Survey sent! Share link: ${url}`);
      }
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not send survey.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Send survey</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">Mock send — logged to the client timeline, no real email/SMS leaves the system.</p>
        <div className="flex flex-col gap-2 text-sm">
          <label>Template *<select required value={f.template_id} onChange={set('template_id')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            <option value="">Pick a template…</option>
            {templatesQ.data?.filter((t) => t.is_active).map((t) => <option key={t.id} value={t.id}>{t.name} ({t.type})</option>)}
          </select></label>
          <label>Client *<select required value={f.client_id} onChange={set('client_id')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            <option value="">Pick a client…</option>
            {clientsQ.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select></label>
          <label>Channel<select value={f.channel} onChange={set('channel')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            <option value="link">Share link</option>
            <option value="email_mock">Email (mock)</option>
            <option value="sms_mock">SMS (mock)</option>
          </select></label>
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Sending…' : 'Send survey'}</button>
        </div>
      </form>
      <ConfirmDialog
        open={confirmSend}
        tone="info"
        title="Send survey?"
        body="The client contact gets a fresh share link. Mock send — logged to the timeline, no real email or SMS leaves the system."
        details={[
          `Template: ${chosenTemplate?.name ?? '—'} (${chosenTemplate?.type ?? '?'})`,
          `Client: ${chosenClient?.name ?? '—'}`,
          `Channel: ${f.channel}`,
        ]}
        confirmLabel="Send survey"
        onCancel={() => setConfirmSend(false)}
        onConfirm={() => void doSend()}
      />
    </div>
  );
}

/* --------------------------------- Templates -------------------------------- */

function TemplatesTab() {
  const { user } = useSession();
  const canEdit = hasRole(user, 'manager', 'admin');
  const [showBuilder, setShowBuilder] = useState(false);
  const [editing, setEditing] = useState<Template | null>(null);
  const toast = useToast();
  const qc = useQueryClient();

  const templatesQ = useQuery({
    queryKey: ['survey-templates'],
    queryFn: async () => (await api.get('/survey-templates')).data.data as Template[],
  });
  const rows = templatesQ.data ?? [];

  return (
    <>
      <div className="flex gap-2">
        <span className="flex-1" />
        {canEdit && <button onClick={() => { setEditing(null); setShowBuilder(true); }} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">+ New template</button>}
      </div>
      {templatesQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading templates…</p>
        : (
          <DataTable<Template>
            rows={rows}
            columns={[
              { key: 'n', header: 'Name', render: (r) => <span className="font-medium">{r.name}</span> },
              { key: 't', header: 'Type', render: (r) => <StatusBadge value={r.type} /> },
              { key: 'q', header: 'Questions', render: (r) => String(r.questions.length) },
              { key: 'a', header: 'Active', render: (r) => (r.is_active ? 'Yes' : 'No') },
              {
                key: 'x', header: 'Actions', render: (r) => canEdit
                  ? <button onClick={() => { setEditing(r); setShowBuilder(true); }} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">Edit</button>
                  : <span className="text-xs text-[var(--text-muted)]">Manager+ only</span>,
              },
            ]}
            empty={<EmptyState title="No templates yet" hint="Managers can build NPS, CSAT, or custom templates with a live preview." />}
          />
        )}
      {showBuilder && (
        <TemplateBuilder
          initial={editing}
          onClose={() => { setShowBuilder(false); setEditing(null); }}
          onDone={() => {
            qc.invalidateQueries({ queryKey: ['survey-templates'] });
            toast('success', 'Template ready — send to a client.');
          }}
        />
      )}
    </>
  );
}

function TemplateBuilder({ initial, onClose, onDone }: { initial: Template | null; onClose: () => void; onDone: () => void }) {
  const [name, setName] = useState(initial?.name ?? '');
  const [type, setType] = useState<'nps' | 'csat' | 'custom'>(initial?.type ?? 'nps');
  const [questions, setQuestions] = useState<{ q: string; scale?: number }[]>(
    initial?.questions.map((q) => ({ q: q.q, scale: q.scale })) ?? [{ q: '', scale: type === 'csat' ? 5 : 10 }],
  );
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!name.trim() || questions.some((q) => !q.q.trim())) {
      setErr('Name and every question need text.');
      return;
    }
    setBusy(true);
    setErr('');
    try {
      const payload = { name: name.trim(), type, questions: questions.map((q) => ({ q: q.q.trim(), scale: q.scale ?? (type === 'csat' ? 5 : 10) })) };
      if (initial) await api.put(`/survey-templates/${initial.id}`, payload);
      else await api.post('/survey-templates', payload);
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not save template.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card my-8 w-full max-w-2xl p-6">
        <h2 className="text-lg font-semibold">{initial ? 'Edit template' : 'New template'}</h2>
        <div className="mt-3 grid gap-4 md:grid-cols-2">
          <div className="flex flex-col gap-2 text-sm">
            <label>Name *<input value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Quarterly NPS" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Type<select value={type} onChange={(e) => setType(e.target.value as typeof type)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
              <option value="nps">NPS (0–10)</option>
              <option value="csat">CSAT (1–5)</option>
              <option value="custom">Custom</option>
            </select></label>
            {questions.map((q, i) => (
              <div key={i} className="rounded-lg border border-[var(--border)] p-2">
                <label className="text-xs text-[var(--text-muted)]">Question {i + 1} *
                  <input value={q.q} onChange={(e) => setQuestions(questions.map((qq, j) => (j === i ? { ...qq, q: e.target.value } : qq)))} placeholder="e.g. How likely are you to recommend us?" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" />
                </label>
                <div className="mt-1 flex items-center gap-2 text-xs">
                  <label>Scale
                    <select value={q.scale ?? 10} onChange={(e) => setQuestions(questions.map((qq, j) => (j === i ? { ...qq, scale: Number(e.target.value) } : qq)))} className="ml-1 rounded border border-[var(--border)] bg-transparent px-1 py-0.5">
                      {[2, 3, 4, 5, 7, 10].map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                  </label>
                  {questions.length > 1 && <button type="button" onClick={() => setQuestions(questions.filter((_, j) => j !== i))} className="text-red-600 underline">Remove</button>}
                </div>
              </div>
            ))}
            {questions.length < 10 && <button type="button" onClick={() => setQuestions([...questions, { q: '', scale: 10 }])} className="rounded-lg border border-dashed border-[var(--border)] px-3 py-2 text-sm">+ Add question</button>}
          </div>
          <div className="rounded-lg border border-[var(--border)] p-3">
            <p className="mb-2 text-xs font-semibold uppercase text-[var(--text-muted)]">Live preview</p>
            <p className="text-sm font-medium">{name || 'Untitled survey'}</p>
            {questions.map((q, i) => (
              <div key={i} className="mt-2">
                <p className="text-sm">{i + 1}. {q.q || <span className="text-[var(--text-muted)]">Question text…</span>}</p>
                <div className="mt-1 flex flex-wrap gap-1">
                  {Array.from({ length: q.scale ?? 10 }, (_, s) => (
                    <span key={s} className="flex h-7 w-7 items-center justify-center rounded border border-[var(--border)] text-xs">{(type === 'csat' ? 1 : 0) + s}</span>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Saving…' : 'Save template'}</button>
        </div>
      </form>
    </div>
  );
}

/* --------------------------------- Analytics -------------------------------- */

interface Analytics {
  nps: { score: number | null; promoters: number; passives: number; detractors: number; total: number; avg: number | null };
  csat_avg: number | null;
  response_rate: number | null;
  totals: { surveys: number; responded: number; pending: number; expired: number };
  trend: { month: string; avg: number | null; count: number }[];
  per_client: { client_id: string; client_name: string; surveys: number; avg_score: number | null; low: boolean }[];
  low_scores: { survey_id: string; client_name: string; score: number; comment: string | null; responded_at: string }[];
  comments: { score: number; comment: string; client_name: string; at: string }[];
}

function AnalyticsTab() {
  const nav = useNavigate();
  const analyticsQ = useQuery({
    queryKey: ['surveys-analytics'],
    queryFn: async () => (await api.get('/surveys-analytics')).data.data as Analytics,
  });

  if (analyticsQ.isLoading) return <p className="text-sm text-[var(--text-muted)]">Crunching survey data…</p>;
  if (analyticsQ.isError)
    return <div className="card p-6 text-sm">Couldn't load analytics. <button className="text-sky-600 underline" onClick={() => analyticsQ.refetch()}>Retry</button></div>;
  const a = analyticsQ.data;
  if (!a) return <p className="text-sm text-[var(--text-muted)]">No analytics yet — send a survey first.</p>;
  const total = a.nps.total || 1;

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard label="NPS score" value={a.nps.score !== null ? String(a.nps.score) : '—'} sub={`${a.nps.promoters} promoters · ${a.nps.detractors} detractors`} />
        <KpiCard label="CSAT average" value={a.csat_avg !== null ? a.csat_avg.toFixed(2) : '—'} sub="1–5 scale" />
        <KpiCard label="Response rate" value={a.response_rate !== null ? `${a.response_rate}%` : '—'} sub={`${a.totals.responded}/${a.totals.surveys} answered`} />
        <KpiCard label="Pending / expired" value={`${a.totals.pending} / ${a.totals.expired}`} sub="follow up on pending" />
      </div>

      <div className="card p-4">
        <h2 className="font-semibold">NPS split</h2>
        <div className="mt-2 flex h-4 overflow-hidden rounded-full text-[11px] text-white">
          <span style={{ width: `${(a.nps.promoters / total) * 100}%` }} className="bg-green-500" title="Promoters" />
          <span style={{ width: `${(a.nps.passives / total) * 100}%` }} className="bg-amber-400" title="Passives" />
          <span style={{ width: `${(a.nps.detractors / total) * 100}%` }} className="bg-red-500" title="Detractors" />
        </div>
        <p className="mt-1 text-xs text-[var(--text-muted)]"><span className="mr-1 inline-block h-2.5 w-2.5 rounded-sm bg-green-500" /> {a.nps.promoters} promoters (9–10) · <span className="mr-1 inline-block h-2.5 w-2.5 rounded-sm bg-amber-400" /> {a.nps.passives} passives (7–8) · <span className="mr-1 inline-block h-2.5 w-2.5 rounded-sm bg-red-500" /> {a.nps.detractors} detractors (0–6)</p>
      </div>

      <div className="card p-4">
        <h2 className="font-semibold">6-month trend</h2>
        <div className="mt-2 flex items-end gap-2">
          {a.trend.map((t) => (
            <div key={t.month} className="flex flex-1 flex-col items-center gap-1" title={`${t.month}: ${t.avg ?? 'no data'} (${t.count})`}>
              <div className="flex h-24 w-full items-end rounded bg-slate-100 dark:bg-slate-800">
                <div style={{ height: t.avg !== null ? `${(t.avg / 10) * 100}%` : '0%' }} className="w-full rounded bg-sky-500" />
              </div>
              <span className="text-[10px] text-[var(--text-muted)]">{t.month.slice(5)}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="card p-4">
        <h2 className="font-semibold">Per-client scores</h2>
        <DataTable
          rows={a.per_client.map((c) => ({ ...c, id: c.client_id }))}
          columns={[
            { key: 'c', header: 'Client', render: (r) => r.client_name },
            { key: 's', header: 'Surveys', render: (r) => String(r.surveys) },
            { key: 'a', header: 'Avg', render: (r) => (r.avg_score !== null ? r.avg_score.toFixed(2) : '—') },
            {
              key: 'f', header: 'Flag', render: (r) => r.low
                ? <button onClick={() => nav('/followups')} className="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">low — create follow-up</button>
                : <span className="text-xs text-[var(--text-muted)]">healthy</span>,
            },
          ]}
          empty={<EmptyState title="No survey data yet" hint="Send your first survey to unlock per-client analytics." />}
        />
      </div>

      <div className="card p-4">
        <h2 className="font-semibold">Comment wall</h2>
        {a.comments.length === 0 ? <p className="mt-1 text-sm text-[var(--text-muted)]">No comments yet.</p> : (
          <ul className="mt-2 flex flex-col gap-2">
            {a.comments.map((c, i) => (
              <li key={i} className="flex gap-2 text-sm">
                <span className={`mt-0.5 h-2.5 w-2.5 shrink-0 rounded-full ${c.score >= 9 ? 'bg-green-500' : c.score >= 7 ? 'bg-amber-400' : 'bg-red-500'}`} title={`Score ${c.score}`} />
                <span>“{c.comment}” <span className="text-xs text-[var(--text-muted)]">— {c.client_name} · <Star size={11} className="mr-0.5 inline" />{c.score}</span></span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
