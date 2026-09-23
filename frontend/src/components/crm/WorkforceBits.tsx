import { useQuery } from '@tanstack/react-query';
import { Printer } from 'lucide-react';
import api from '../../lib/apiClient';
import { hasRole, useSession } from '../../store/session';

export interface DirectoryMember {
  id: number;
  name: string;
  email: string;
  role: string;
  team_id: number | null;
  team_name?: string;
  is_active: boolean;
  last_login_at: string | null;
}

export function downloadCsv(name: string, headers: string[], rows: (string | number)[][]) {
  const esc = (v: string | number) => `"${String(v).replace(/"/g, '""')}"`;
  const csv = [headers.map(esc).join(','), ...rows.map((r) => r.map(esc).join(','))].join('\n');
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  a.click();
  URL.revokeObjectURL(url);
}

export function PrintButton() {
  return (
    <button onClick={() => window.print()} className="rounded-lg border border-[var(--border)] px-3 py-2 text-sm font-medium">
      <span className="inline-flex items-center gap-1.5"><Printer size={14} /> Print / PDF</span>
    </button>
  );
}

/** Member scope: reps see themselves; managers/admins pick from the directory. */
export function useMembers() {
  const { user } = useSession();
  const q = useQuery({
    queryKey: ['hr-directory'],
    queryFn: async () => (await api.get('/hr/directory', { params: { per_page: 100 } })).data.data as DirectoryMember[],
  });
  const members = q.data ?? [];
  const canPick = hasRole(user, 'manager', 'admin', 'superadmin');
  return { user, members, canPick, isLoading: q.isLoading };
}

export function MemberPicker({ value, onChange, members }: { value: number | null; onChange: (id: number) => void; members: DirectoryMember[] }) {
  const { user } = useSession();
  if (!hasRole(user, 'manager', 'admin', 'superadmin')) return null;
  return (
    <select value={value ?? ''} onChange={(e) => onChange(Number(e.target.value))} className="card px-3 py-2 text-sm" aria-label="Pick a team member">
      {members.map((m) => (
        <option key={m.id} value={m.id}>{m.name} · {m.role.replace('_', ' ')}</option>
      ))}
    </select>
  );
}

export function MonthNav({ month, onChange }: { month: string; onChange: (m: string) => void }) {
  const shift = (delta: number) => {
    const [y, m] = month.split('-').map(Number);
    const t = new Date(y, m - 1 + delta, 1);
    const p = (n: number) => String(n).padStart(2, '0');
    onChange(`${t.getFullYear()}-${p(t.getMonth() + 1)}`);
  };
  const label = new Date(month + '-01T00:00:00').toLocaleString('en-PH', { month: 'long', year: 'numeric' });
  const current = new Date().toISOString().slice(0, 7);
  return (
    <div className="card flex items-center justify-between px-3 py-2">
      <button onClick={() => shift(-1)} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs" aria-label="Previous month">← Prev</button>
      <span className="text-sm font-semibold">{label}</span>
      <span className="flex gap-1.5">
        {month !== current && <button onClick={() => onChange(current)} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs">This month</button>}
        <button onClick={() => shift(1)} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs" aria-label="Next month">Next →</button>
      </span>
    </div>
  );
}

export function currentMonth(): string {
  return new Date().toISOString().slice(0, 7);
}
