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

// Corrupted storage must never blank the app: fall back to signed-out state
// and drop the bad value so the next load is clean too.
function initialUser(): SessionUser | null {
  try {
    const raw = sessionStorage.getItem('crm.user');
    if (!raw) return null;
    const parsed: unknown = JSON.parse(raw);
    if (parsed && typeof parsed === 'object' && 'id' in parsed && 'email' in parsed) {
      return parsed as SessionUser;
    }
    sessionStorage.removeItem('crm.user');
    return null;
  } catch {
    sessionStorage.removeItem('crm.user');
    return null;
  }
}

export const useSession = create<SessionState>((set) => ({
  user: initialUser(),
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
    // Preserve appearance across sessions — only auth state is cleared.
    const theme = sessionStorage.getItem('crm.theme');
    sessionStorage.clear();
    if (theme) sessionStorage.setItem('crm.theme', theme);
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
