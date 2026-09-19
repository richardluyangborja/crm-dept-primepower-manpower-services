import { create } from 'zustand';

export type Role = 'superadmin' | 'admin' | 'manager' | 'sales_rep';
export interface SessionUser {
  id: number;
  name: string;
  email: string;
  role: Role;
  team_id: number | null;
}

interface SessionState {
  user: SessionUser | null;
  theme: 'light' | 'dark' | 'system';
  setUser: (u: SessionUser | null) => void;
  setTheme: (t: 'light' | 'dark' | 'system') => void;
  logout: () => void;
}

const initialTheme = (): 'light' | 'dark' | 'system' =>
  (sessionStorage.getItem('crm.theme') as 'light' | 'dark' | 'system') || 'system';

export const useSession = create<SessionState>((set) => ({
  user: JSON.parse(sessionStorage.getItem('crm.user') ?? 'null'),
  theme: initialTheme(),
  setUser: (u) => {
    if (u) sessionStorage.setItem('crm.user', JSON.stringify(u));
    else sessionStorage.removeItem('crm.user');
    set({ user: u });
  },
  setTheme: (t) => {
    sessionStorage.setItem('crm.theme', t);
    applyTheme(t);
    set({ theme: t });
  },
  logout: () => {
    sessionStorage.clear();
    set({ user: null });
  },
}));

export function applyTheme(t: 'light' | 'dark' | 'system') {
  const dark =
    t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.classList.toggle('dark', dark);
}

export function hasRole(user: SessionUser | null, ...roles: Role[]): boolean {
  if (!user) return false;
  if (user.role === 'superadmin') return true;
  return roles.includes(user.role);
}
