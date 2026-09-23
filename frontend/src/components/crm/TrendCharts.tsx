import {
  CartesianGrid, ComposedChart, Bar, Line, LineChart,
  ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import { EmptyState } from '../ui/EmptyState';
import { AiBadge } from './InsightBits';

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
                <Line type="monotone" dataKey="nps_avg" name="NPS avg" stroke="#0ea5e9" strokeWidth={2} dot={false} connectNulls />
              </LineChart>
            </ResponsiveContainer>
          </div>
        )}
      </div>
    </div>
  );
}
