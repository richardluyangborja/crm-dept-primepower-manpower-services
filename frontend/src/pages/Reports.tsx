import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { KpiCard } from '../components/ui/KpiCard';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { DataTable } from '../components/ui/DataTable';
import { useToast } from '../components/ui/Toaster';
import { AiBadge, FeedbackThumbs } from '../components/crm/InsightBits';

interface Pack {
  narrative: string;
  tables: {
    pipeline_by_stage: { stage: string; count: number; value_centavos: number }[];
    forecast: { open_centavos: number; weighted_centavos: number; open_count: number; win_rate: number | null };
    satisfaction: { nps: { score: number | null; promoters: number; passives: number; detractors: number }; csat_avg: number | null; response_rate: number | null; totals: { surveys: number; responded: number; pending: number; expired: number } };
    activity_by_type: Record<string, number>;
    activity_by_owner: { owner_id: number; owner_name?: string; count: number }[];
    followup_compliance: { open: number; done: number; overdue: number };
    risks: { client_id: number; client_name: string; owner_name?: string; level: string; drivers: string[]; nba: { kind: string; title: string; link: string }[] }[];
    comment_sentiment: { client_name?: string; score: number; comment: string; sentiment: { label: string; score: number } }[];
  };
  meta: { ai_preview: boolean; generated_at: string; period: { from: string; to: string } };
}
interface SavedPack {
  id: number;
  type: string;
  period_from: string;
  period_to: string;
  team_id: number | null;
  created_at: string;
}

function downloadCsv(name: string, headers: string[], rows: (string | number)[][]) {
  const esc = (v: string | number) => `"${String(v).replace(/"/g, '""')}"`;
  const csv = [headers.map(esc).join(','), ...rows.map((r) => r.map(esc).join(','))].join('\n');
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  a.click();
  URL.revokeObjectURL(url);
}

function apiErr(e: unknown, fallback: string): string {
  if (typeof e === 'object' && e !== null && 'response' in e) {
    const r = (e as { response?: { data?: { message?: string } } }).response;
    if (r?.data?.message) return r.data.message;
  }
  return fallback;
}

export function ReportsPage() {
  const [type, setType] = useState<'weekly' | 'monthly'>('weekly');
  const toast = useToast();
  const qc = useQueryClient();

  const packQ = useQuery({
    queryKey: ['report', type],
    queryFn: async () => (await api.get(`/reports/${type}`)).data.data as Pack,
  });
  const savedQ = useQuery({
    queryKey: ['reports-saved'],
    queryFn: async () => (await api.get('/reports')).data.data as SavedPack[],
  });

  const genMut = useMutation({
    mutationFn: async () => (await api.post('/reports/generate', { type })).data,
    onSuccess: (d) => {
      toast('success', d.message ?? `${type} pack ready — download CSV.`);
      qc.invalidateQueries({ queryKey: ['reports-saved'] });
    },
    onError: (e) => toast('error', apiErr(e, 'Could not generate pack.')),
  });

  const pack = packQ.data;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-xl font-bold">Management reports</h1>
          <p className="text-sm text-[var(--text-muted)]">One-click weekly/monthly packs for decision support. <AiBadge /></p>
        </div>
        <div className="flex gap-2">
          <div className="flex gap-1">
            {(['weekly', 'monthly'] as const).map((t) => (
              <button key={t} onClick={() => setType(t)}
                className={`rounded-lg px-4 py-1.5 text-sm font-medium capitalize ${type === t ? 'bg-sky-600 text-white' : 'border border-[var(--border)]'}`}>
                {t}
              </button>
            ))}
          </div>
          <button onClick={() => genMut.mutate()} disabled={genMut.isPending}
            className="rounded-lg bg-sky-600 px-4 py-1.5 text-sm font-semibold text-white disabled:opacity-50">
            {genMut.isPending ? 'Generating…' : 'Generate pack'}
          </button>
          <button onClick={() => window.print()} className="rounded-lg border border-[var(--border)] px-4 py-1.5 text-sm">🖨 Print / PDF</button>
        </div>
      </div>

      {packQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Building your {type} pack…</p>
        : packQ.isError ? (
          <div className="card p-6 text-sm">
            {(() => {
              const err = packQ.error as { response?: { status?: number } };
              return err?.response?.status === 403
                ? 'Reports are available to managers and above — ask your manager for access.'
                : <>Couldn't build the pack. <button className="text-sky-600 underline" onClick={() => packQ.refetch()}>Retry</button></>;
            })()}
          </div>
        ) : pack ? <PackView pack={pack} type={type} /> : null}

      {(savedQ.data ?? []).length > 0 && (
        <div className="card p-4">
          <h2 className="mb-2 font-semibold">Generated packs</h2>
          <ul className="flex flex-col gap-1 text-sm">
            {savedQ.data!.map((r) => (
              <li key={r.id} className="flex items-center justify-between border-b border-[var(--border)] pb-1 last:border-0">
                <span className="capitalize">{r.type} <span className="text-xs text-[var(--text-muted)]">{r.period_from} → {r.period_to}</span></span>
                <span className="text-xs text-[var(--text-muted)]">#{r.id} · {new Date(r.created_at).toLocaleString('en-PH')}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

function PackView({ pack, type }: { pack: Pack; type: string }) {
  const t = pack.tables;
  const csv = {
    pipeline: () => downloadCsv(`${type}-pipeline.csv`, ['stage', 'count', 'value_php'],
      t.pipeline_by_stage.map((r) => [r.stage, r.count, (r.value_centavos / 100).toFixed(0)])),
    risks: () => downloadCsv(`${type}-risks.csv`, ['client', 'owner', 'level', 'drivers'],
      t.risks.map((r) => [r.client_name, r.owner_name ?? '', r.level, r.drivers.join('; ')])),
    satisfaction: () => downloadCsv(`${type}-satisfaction.csv`, ['metric', 'value'], [
      ['nps', t.satisfaction.nps.score ?? ''],
      ['csat_avg', t.satisfaction.csat_avg ?? ''],
      ['response_rate', t.satisfaction.response_rate ?? ''],
      ['surveys', t.satisfaction.totals.surveys],
      ['responded', t.satisfaction.totals.responded],
    ]),
  };

  return (
    <>
      <div className="card border-l-4 border-l-violet-500 p-4">
        <div className="mb-1 flex items-center gap-2">
          <h2 className="font-semibold">Executive summary</h2>
          <AiBadge />
        </div>
        <p className="text-sm leading-relaxed">{pack.narrative}</p>
        <p className="mt-1 text-xs text-[var(--text-muted)]">Period {pack.meta.period.from} → {pack.meta.period.to} · generated {new Date(pack.meta.generated_at).toLocaleString('en-PH')}</p>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard label="Open pipeline" value={formatPHP(t.forecast.open_centavos)} sub={`${t.forecast.open_count} open deals`} />
        <KpiCard label="Weighted forecast" value={formatPHP(t.forecast.weighted_centavos)} sub="Value × probability" />
        <KpiCard label="NPS" value={t.satisfaction.nps.score !== null ? String(t.satisfaction.nps.score) : '—'} sub={`${t.satisfaction.totals.responded}/${t.satisfaction.totals.surveys} responded`} />
        <KpiCard label="Overdue follow-ups" value={String(t.followup_compliance.overdue)} sub={`${t.followup_compliance.done} done`} />
      </div>

      <div className="card p-4">
        <div className="mb-2 flex items-center justify-between">
          <h2 className="font-semibold">Pipeline by stage</h2>
          <button onClick={csv.pipeline} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">⬇ CSV</button>
        </div>
        <DataTable
          rows={t.pipeline_by_stage.map((r) => ({ ...r, id: r.stage }))}
          columns={[
            { key: 's', header: 'Stage', render: (r) => <StatusBadge value={r.stage} /> },
            { key: 'c', header: 'Deals', render: (r) => String(r.count) },
            { key: 'v', header: 'Value', render: (r) => <span className="tabular-nums">{formatPHP(r.value_centavos)}</span> },
          ]}
          empty={<EmptyState title="No pipeline data" hint="Create deals to fill this table." />}
        />
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <div className="card p-4">
          <h2 className="mb-2 font-semibold">Activity & follow-up compliance</h2>
          <ul className="flex flex-col gap-1 text-sm">
            {Object.entries(t.activity_by_type).map(([k, v]) => (
              <li key={k} className="flex justify-between border-b border-[var(--border)] pb-1 last:border-0">
                <span className="capitalize">{k.replace('_', ' ')}</span><span className="font-semibold">{v}</span>
              </li>
            ))}
            {Object.keys(t.activity_by_type).length === 0 && <li className="text-xs text-[var(--text-muted)]">No touchpoints in period.</li>}
          </ul>
          <p className="mt-2 text-sm">Follow-ups: {t.followup_compliance.done} done · {t.followup_compliance.open} open · <strong className="text-red-600">{t.followup_compliance.overdue} overdue</strong></p>
          {t.activity_by_owner.length > 0 && (
            <>
              <h3 className="mb-1 mt-3 text-sm font-semibold">Per owner</h3>
              <ul className="flex flex-col gap-1 text-sm">
                {t.activity_by_owner.map((o) => (
                  <li key={o.owner_id} className="flex justify-between border-b border-[var(--border)] pb-1 last:border-0">
                    <span>{o.owner_name ?? `#${o.owner_id}`}</span><span className="font-semibold">{o.count}</span>
                  </li>
                ))}
              </ul>
            </>
          )}
        </div>

        <div className="card p-4">
          <div className="mb-2 flex items-center justify-between">
            <div className="flex items-center gap-2"><h2 className="font-semibold">Risks & recommendations</h2><AiBadge /></div>
            <button onClick={csv.risks} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">⬇ CSV</button>
          </div>
          {t.risks.length === 0 ? <p className="text-sm text-[var(--text-muted)]">No at-risk clients in scope.</p> : (
            <ul className="flex flex-col gap-2 text-sm">
              {t.risks.map((r) => (
                <li key={r.client_id} className="border-b border-[var(--border)] pb-2 last:border-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge value={r.level} />
                    <span className="font-medium">{r.client_name}</span>
                    <FeedbackThumbs insightKey={`risk:${r.client_id}`} />
                  </div>
                  <p className="mt-0.5 text-xs text-[var(--text-muted)]">{r.drivers.join(' · ')}</p>
                  {r.nba.map((a, i) => (
                    <Link key={i} to={a.link} className="mt-0.5 block text-xs text-sky-700 dark:text-sky-300">→ {a.title}</Link>
                  ))}
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      <div className="card p-4">
        <div className="mb-2 flex items-center justify-between">
          <h2 className="font-semibold">Satisfaction & comment sentiment</h2>
          <button onClick={csv.satisfaction} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">⬇ CSV</button>
        </div>
        <p className="text-sm">NPS <strong>{t.satisfaction.nps.score ?? '—'}</strong> ({t.satisfaction.nps.promoters}👍 {t.satisfaction.nps.passives}😐 {t.satisfaction.nps.detractors}👎) · CSAT <strong>{t.satisfaction.csat_avg ?? '—'}</strong> · response rate <strong>{t.satisfaction.response_rate ?? '—'}%</strong></p>
        {t.comment_sentiment.length === 0 ? <p className="mt-1 text-xs text-[var(--text-muted)]">No comments in period.</p> : (
          <ul className="mt-2 flex flex-col gap-1.5 text-sm">
            {t.comment_sentiment.map((c, i) => (
              <li key={i} className="flex gap-2">
                <span title={`Sentiment: ${c.sentiment.label}`}
                  className={`mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${c.sentiment.label === 'positive' ? 'bg-green-500' : c.sentiment.label === 'negative' ? 'bg-red-500' : 'bg-slate-300'}`} />
                <span>“{c.comment}” <span className="text-xs text-[var(--text-muted)]">— {c.client_name} · ★{c.score}</span></span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </>
  );
}
