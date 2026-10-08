import {
  Bar, BarChart, CartesianGrid, Cell, ComposedChart, Legend, Line,
  Tooltip, XAxis, YAxis,
} from 'recharts';
import { formatPHP } from '../../lib/format';

export interface ReportPack {
  narrative: string;
  tables: {
    pipeline_by_stage: { stage: string; count: number; value_centavos: number }[];
    forecast: { open_centavos: number; weighted_centavos: number; open_count: number; win_rate: number | null };
    satisfaction: { nps: { score: number | null; promoters: number; passives: number; detractors: number }; csat_avg: number | null; response_rate: number | null; totals: { surveys: number; responded: number; pending: number; expired: number } };
    activity_by_type: Record<string, number>;
    activity_by_owner: { owner_id: number; owner_name?: string; count: number }[];
    followup_compliance: { open: number; done: number; overdue: number };
    risks: { client_id: string; client_name: string; owner_name?: string; level: string; drivers: string[]; nba: { kind: string; title: string; link: string }[] }[];
    comment_sentiment: { client_name?: string; score: number; comment: string; sentiment: { label: string; score: number } }[];
    deal_trend_12m: { month: string; won: number; lost: number; win_rate: number | null; new_opps: number }[];
    satisfaction_trend_12m: { month: string; avg: number | null; nps: number | null; responses: number }[];
    satisfaction_by_client: { client_name: string | null; surveys: number; responded: number; avg_score: number | null; low: boolean }[];
  };
  meta: { generated_at: string; period: { from: string; to: string } };
}

const STAGE_COLORS: Record<string, string> = {
  new: '#38bdf8', contacted: '#818cf8', qualified: '#a78bfa', proposal: '#f472b6',
  negotiation: '#f59e0b', contract: '#fb923c', won: '#16a34a', lost: '#94a3b8',
};

/** Fixed print width (A4 portrait minus @page margins). Never ResponsiveContainer — it measures 0 in print. */
const W = 680;

function monthLabel(m: unknown): string {
  const d = new Date(`${m}-01T00:00:00`);
  const short = d.toLocaleDateString('en-PH', { month: 'short' });
  return d.getMonth() === 0 ? `${short} '${String(d.getFullYear()).slice(2)}` : short;
}

/**
 * Print-only management report (hidden on screen, shown in print).
 * Reads like a real board pack: cover, summary, figures, charts, tables.
 */
export function ManagementReport({ pack, type, orgName, preparedBy }: {
  pack: ReportPack;
  type: string;
  orgName: string;
  preparedBy: string;
}) {
  const t = pack.tables;
  const f = t.forecast;
  const s = t.satisfaction;
  const c = t.followup_compliance;
  const totalActivity = Object.values(t.activity_by_type).reduce((a, v) => a + v, 0);
  const at = new Date(pack.meta.generated_at).toLocaleString('en-PH');

  return (
    <div className="mgmt-report hidden print:block" aria-label="Management report printout">
      {/* Cover */}
      <section>
        <p style={{ color: '#0ea5e9', fontWeight: 700 }}>{orgName}</p>
        <h1 className="font-bold">Management Report — {type === 'weekly' ? 'Weekly' : 'Monthly'} Pack</h1>
        <p>Period {pack.meta.period.from} → {pack.meta.period.to}</p>
        <p>Prepared by {preparedBy} · Generated {at}</p>
        <p style={{ color: '#64748b' }}>Confidential — for management use. Figures come from live CRM data; AI-labeled insights need verification before board use.</p>
      </section>

      {/* 1 · Executive summary */}
      <section>
        <h2 className="font-bold">1. Executive summary</h2>
        <p>{pack.narrative}</p>
      </section>

      {/* 2 · Key figures */}
      <section>
        <h2 className="font-bold">2. Key figures</h2>
        <table>
          <tbody>
            <tr><th>Open pipeline</th><td>{formatPHP(f.open_centavos)} across {f.open_count} open deals</td></tr>
            <tr><th>Weighted forecast</th><td>{formatPHP(f.weighted_centavos)} (value × probability)</td></tr>
            <tr><th>Win rate</th><td>{f.win_rate !== null ? `${f.win_rate}% on closed deals` : 'No closed deals in scope'}</td></tr>
            <tr><th>Net Promoter Score</th><td>{s.nps.score !== null ? `${s.nps.score} (${s.nps.promoters} promoters · ${s.nps.passives} passives · ${s.nps.detractors} detractors)` : 'No NPS responses in scope'}</td></tr>
            <tr><th>Customer satisfaction</th><td>{s.csat_avg !== null ? `${s.csat_avg} / 5 average` : 'No CSAT responses in scope'}</td></tr>
            <tr><th>Survey response rate</th><td>{s.response_rate !== null ? `${s.response_rate}% (${s.totals.responded}/${s.totals.surveys})` : '—'}</td></tr>
            <tr><th>Touchpoints logged</th><td>{totalActivity}</td></tr>
            <tr><th>Follow-ups</th><td>{c.done} done · {c.open} open · {c.overdue} overdue</td></tr>
            <tr><th>At-risk clients</th><td>{t.risks.length}</td></tr>
          </tbody>
        </table>
      </section>

      {/* 3 · Sales performance */}
      <section>
        <h2 className="font-bold">3. Sales performance — trailing 12 months</h2>
        <ComposedChart width={W} height={260} data={t.deal_trend_12m} margin={{ top: 4, right: 4, bottom: 0, left: -12 }}>
          <CartesianGrid strokeDasharray="3 3" opacity={0.4} />
          <XAxis dataKey="month" tickFormatter={monthLabel} fontSize={10} interval={0} angle={-25} dy={8} height={44} />
          <YAxis yAxisId="left" allowDecimals={false} fontSize={10} />
          <YAxis yAxisId="right" orientation="right" domain={[0, 100]} fontSize={10} />
          <Tooltip labelFormatter={monthLabel} formatter={(v, name) => [v ?? '—', name === 'win_rate' ? 'Win rate %' : name === 'new_opps' ? 'New deals' : String(name ?? '')]} />
          <Legend wrapperStyle={{ fontSize: 10 }} />
          <Bar yAxisId="left" dataKey="won" name="Won" fill="#16a34a" />
          <Bar yAxisId="left" dataKey="lost" name="Lost" fill="#94a3b8" />
          <Line yAxisId="right" type="monotone" dataKey="win_rate" name="Win rate %" stroke="#f59e0b" strokeWidth={2} dot={false} connectNulls />
        </ComposedChart>
        <h3 className="mt-2 font-semibold">Pipeline by stage</h3>
        <table>
          <thead><tr><th>Stage</th><th>Deals</th><th>Value</th></tr></thead>
          <tbody>
            {t.pipeline_by_stage.map((r) => (
              <tr key={r.stage}><td className="capitalize">{r.stage}</td><td>{r.count}</td><td>{formatPHP(r.value_centavos)}</td></tr>
            ))}
          </tbody>
        </table>
        <BarChart width={W} height={180} data={t.pipeline_by_stage} margin={{ top: 8, right: 4, bottom: 0, left: -12 }}>
          <CartesianGrid strokeDasharray="3 3" opacity={0.4} />
          <XAxis dataKey="stage" fontSize={10} interval={0} angle={-20} dy={6} height={40} />
          <YAxis allowDecimals={false} fontSize={10} />
          <Tooltip formatter={(v) => [`${v} deals`, 'Deals']} />
          <Bar dataKey="count" name="Deals">
            {t.pipeline_by_stage.map((r) => <Cell key={r.stage} fill={STAGE_COLORS[r.stage] ?? '#64748b'} />)}
          </Bar>
        </BarChart>
      </section>

      {/* 4 · Client satisfaction */}
      <section>
        <h2 className="font-bold">4. Client satisfaction — trailing 12 months</h2>
        <ComposedChart width={W} height={260} data={t.satisfaction_trend_12m} margin={{ top: 4, right: 4, bottom: 0, left: -12 }}>
          <CartesianGrid strokeDasharray="3 3" opacity={0.4} />
          <XAxis dataKey="month" tickFormatter={monthLabel} fontSize={10} interval={0} angle={-25} dy={8} height={44} />
          <YAxis yAxisId="left" domain={[0, 10]} fontSize={10} />
          <YAxis yAxisId="right" orientation="right" domain={[-100, 100]} fontSize={10} />
          <Tooltip labelFormatter={monthLabel} formatter={(v, name) => [v ?? '—', name === 'avg' ? 'Avg score' : name === 'nps' ? 'NPS' : 'Responses']} />
          <Legend wrapperStyle={{ fontSize: 10 }} />
          <Line yAxisId="left" type="monotone" dataKey="avg" name="Avg score" stroke="#0ea5e9" strokeWidth={2} dot={false} connectNulls />
          <Line yAxisId="right" type="monotone" dataKey="nps" name="NPS" stroke="#8b5cf6" strokeWidth={2} dot={false} connectNulls />
        </ComposedChart>
        <h3 className="mt-2 font-semibold">Satisfaction by client</h3>
        <table>
          <thead><tr><th>Client</th><th>Surveys</th><th>Responded</th><th>Avg score</th><th>Flag</th></tr></thead>
          <tbody>
            {t.satisfaction_by_client.map((r, i) => (
              <tr key={i}><td>{r.client_name ?? '—'}</td><td>{r.surveys}</td><td>{r.responded}</td><td>{r.avg_score ?? '—'}</td><td>{r.low ? 'Needs attention' : '—'}</td></tr>
            ))}
            {t.satisfaction_by_client.length === 0 && <tr><td colSpan={5}>No surveys in period.</td></tr>}
          </tbody>
        </table>
      </section>

      {/* 5 · Risks */}
      <section>
        <h2 className="font-bold">5. Risks & recommendations</h2>
        {t.risks.length === 0 ? <p>No at-risk clients in scope.</p> : (
          <table>
            <thead><tr><th>Level</th><th>Client</th><th>Owner</th><th>Drivers</th><th>Recommended next step</th></tr></thead>
            <tbody>
              {t.risks.map((r) => (
                <tr key={r.client_id}>
                  <td className="capitalize">{r.level}</td>
                  <td>{r.client_name}</td>
                  <td>{r.owner_name ?? '—'}</td>
                  <td>{r.drivers.join('; ')}</td>
                  <td>{r.nba.map((a) => a.title).join(' / ') || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>

      {/* 6 · Activity */}
      <section>
        <h2 className="font-bold">6. Activity & follow-up compliance</h2>
        <table>
          <tbody>
            {Object.entries(t.activity_by_type).map(([k, v]) => (
              <tr key={k}><th className="capitalize">{k.replace('_', ' ')}</th><td>{v}</td></tr>
            ))}
            <tr><th>Follow-ups done</th><td>{c.done}</td></tr>
            <tr><th>Follow-ups open</th><td>{c.open}</td></tr>
            <tr><th>Follow-ups overdue</th><td>{c.overdue}</td></tr>
          </tbody>
        </table>
        {t.activity_by_owner.length > 0 && (
          <>
            <h3 className="mt-2 font-semibold">Touchpoints per owner</h3>
            <table>
              <thead><tr><th>Owner</th><th>Touchpoints</th></tr></thead>
              <tbody>
                {t.activity_by_owner.map((o) => (
                  <tr key={o.owner_id}><td>{o.owner_name ?? `#${o.owner_id}`}</td><td>{o.count}</td></tr>
                ))}
              </tbody>
            </table>
          </>
        )}
      </section>

      {/* 7 · Voices */}
      {t.comment_sentiment.length > 0 && (
        <section>
          <h2 className="font-bold">7. Client voices</h2>
          <table>
            <thead><tr><th>Client</th><th>Score</th><th>Comment</th></tr></thead>
            <tbody>
              {t.comment_sentiment.slice(0, 12).map((cm, i) => (
                <tr key={i}><td>{cm.client_name ?? '—'}</td><td>{cm.score}</td><td>“{cm.comment}”</td></tr>
              ))}
            </tbody>
          </table>
        </section>
      )}

      <div className="mgmt-page-footer">
        <span>{orgName} · Management Report ({pack.meta.period.from} → {pack.meta.period.to}) · Confidential</span>
        <span style={{ float: 'right' }}>Page <span className="pagenum" /></span>
      </div>
    </div>
  );
}
