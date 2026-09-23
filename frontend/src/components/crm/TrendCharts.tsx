import {
  CartesianGrid, ComposedChart, Bar, Line, LineChart, Pie, PieChart, Cell,
  ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../lib/apiClient';
import { EmptyState } from '../ui/EmptyState';
import { AiBadge } from './InsightBits';

/** Admin-configured stage labels (Settings → Master data); keys stay fixed. */
export function useStageLabels(): Record<string, string> {
  const q = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get('/settings')).data.data as Record<string, unknown>,
    staleTime: 60000,
  });
  const list = Array.isArray(q.data?.pipeline_stages) ? (q.data.pipeline_stages as { key: string; label: string }[]) : [];
  const map: Record<string, string> = {};
  for (const s of list) {
    if (s.key && s.label) map[s.key] = s.label;
  }
  return map;
}

export interface MonthPoint {
  month: string;
  won: number;
  lost: number;
  win_rate: number | null;
  new_opps: number;
  nps_avg: number | null;
}

/** Shared trailing-6-month trend visuals (Dashboard + Reports). */
export function TrendsCard({ trend }: { trend: MonthPoint[] }) {
  const hasCloses = trend.some((t) => t.won > 0 || t.lost > 0);
  const hasNps = trend.some((t) => t.nps_avg !== null);
  if (!hasCloses && !hasNps) {
    return (
      <EmptyState
        title="No trend data yet"
        hint="Close deals and collect survey responses — six months of win-rate and satisfaction trends will chart here."
      />
    );
  }
  const short = (m: string) => m.slice(5);
  return (
    <div className="card p-4">
      <div className="mb-2 flex items-center gap-2">
        <h2 className="font-semibold">Trailing 6-month trends</h2>
        <AiBadge />
      </div>
      <div className="grid gap-4 md:grid-cols-2">
        {hasCloses && (
          <div>
            <p className="mb-1 text-xs text-[var(--text-muted)]">Deals closed + win rate</p>
            <ResponsiveContainer width="100%" height={200}>
              <ComposedChart data={trend}>
                <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                <XAxis dataKey="month" tickFormatter={short} fontSize={11} />
                <YAxis yAxisId="left" allowDecimals={false} fontSize={11} />
                <YAxis yAxisId="right" orientation="right" domain={[0, 100]} fontSize={11} />
                <Tooltip formatter={(v, name) => [v ?? '—', name === 'win_rate' ? 'Win rate %' : String(name ?? '')]} />
                <Bar yAxisId="left" dataKey="won" name="Won" fill="#16a34a" />
                <Bar yAxisId="left" dataKey="lost" name="Lost" fill="#94a3b8" />
                <Line yAxisId="right" type="monotone" dataKey="win_rate" name="win_rate" stroke="#f59e0b" strokeWidth={2} dot={false} connectNulls />
              </ComposedChart>
            </ResponsiveContainer>
          </div>
        )}
        {hasNps && (
          <div>
            <p className="mb-1 text-xs text-[var(--text-muted)]">Satisfaction average</p>
            <ResponsiveContainer width="100%" height={200}>
              <LineChart data={trend}>
                <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                <XAxis dataKey="month" tickFormatter={short} fontSize={11} />
                <YAxis domain={[-100, 100]} fontSize={11} />
                <Tooltip />
                <Line type="monotone" dataKey="nps_avg" name="Satisfaction" stroke="#0ea5e9" strokeWidth={2} dot={false} connectNulls />
              </LineChart>
            </ResponsiveContainer>
          </div>
        )}
      </div>
    </div>
  );
}

const STAGE_COLORS: Record<string, string> = {
  new: '#38bdf8',
  contacted: '#818cf8',
  qualified: '#a78bfa',
  proposal: '#f472b6',
  negotiation: '#f59e0b',
  contract: '#fb923c',
  won: '#16a34a',
  lost: '#94a3b8',
};

/** Donut of open + closed deals by stage (Dashboard). Slices jump to the board. */
export function StageDonut({ byStage, labels }: { byStage: { stage: string; count: number; value: number }[]; labels: Record<string, string> }) {
  const nav = useNavigate();
  const data = (byStage ?? []).filter((s) => s.count > 0);
  if (data.length === 0) {
    return <EmptyState title="No deals yet" hint="Create the first deal and the stage mix charts here." />;
  }
  return (
    <div className="card p-4">
      <div className="mb-2 flex items-center justify-between">
        <h2 className="font-semibold">Deals by stage</h2>
        <button onClick={() => nav('/pipeline')} className="text-xs text-sky-700 hover:underline dark:text-sky-300">Open board →</button>
      </div>
      <ResponsiveContainer width="100%" height={210}>
        <PieChart>
          <Pie
            data={data}
            dataKey="count"
            nameKey="stage"
            innerRadius={55}
            outerRadius={85}
            paddingAngle={2}
            onClick={() => nav('/pipeline')}
            className="cursor-pointer outline-none"
          >
            {data.map((s) => (
              <Cell key={s.stage} fill={STAGE_COLORS[s.stage] ?? '#64748b'} />
            ))}
          </Pie>
          <Tooltip formatter={(v, _name, item) => [`${v} deals`, labels[(item?.payload as { stage: string })?.stage] ?? (item?.payload as { stage: string })?.stage]} />
        </PieChart>
      </ResponsiveContainer>
      <ul className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs">
        {data.map((s) => (
          <li key={s.stage} className="flex items-center gap-1">
            <span className="h-2.5 w-2.5 rounded-sm" style={{ background: STAGE_COLORS[s.stage] ?? '#64748b' }} />
            {labels[s.stage] ?? s.stage} <strong className="tabular-nums">{s.count}</strong>
          </li>
        ))}
      </ul>
    </div>
  );
}
