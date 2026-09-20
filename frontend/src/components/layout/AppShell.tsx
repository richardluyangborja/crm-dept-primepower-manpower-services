import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { Bell, LayoutDashboard, LogOut, Menu, Search, Users, KanbanSquare, MessagesSquare, Star, BellRing, BarChart3, Settings, CircleHelp, Wallet, Factory } from 'lucide-react';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../lib/apiClient';
import { hasRole, useSession } from '../../store/session';
import { ThemeToggle } from '../ui/ThemeToggle';
import { useToast } from '../ui/Toaster';
import { useIdleTimer } from '../../hooks/useIdleTimer';
import { TourCard, useTour } from '../ui/Tour';

const groups: { label: string; links: { to: string; label: string; icon: React.ReactNode; roles?: string[] }[] }[] = [
  { label: '', links: [{ to: '/', label: 'Dashboard', icon: <LayoutDashboard size={18} /> }] },
  {
    label: 'Sales',
    links: [
      { to: '/leads', label: 'Leads & Clients', icon: <Users size={18} /> },
      { to: '/pipeline', label: 'Opportunity Pipeline', icon: <KanbanSquare size={18} /> },
      { to: '/followups', label: 'Follow-ups', icon: <BellRing size={18} /> },
    ],
  },
  {
    label: 'Engagement',
    links: [
      { to: '/comms', label: 'Communications', icon: <MessagesSquare size={18} /> },
      { to: '/surveys', label: 'Satisfaction & Surveys', icon: <Star size={18} /> },
    ],
  },
  { label: 'AI & Analytics', links: [{ to: '/reports', label: 'Reports', icon: <BarChart3 size={18} /> }] },
  { label: 'Finance', links: [{ to: '/finance', label: 'Receivables', icon: <Wallet size={18} /> }] },
  { label: 'Operations', links: [{ to: '/operations', label: 'Staffing Board', icon: <Factory size={18} /> }] },
  { label: 'System', links: [{ to: '/settings', label: 'Settings', icon: <Settings size={18} /> }] },
];

export function AppShell() {
  const { user, logout } = useSession();
  const [open, setOpen] = useState(false);
  const nav = useNavigate();
  const toast = useToast();

  // 5-min idle timeout (specs/16): warn at 4:00, force logout at 5:00.
  // The backend is authoritative — any silence past 300s gets 401 anyway.
  const idle = useIdleTimer({
    onTimeout: () => {
      logout();
      toast('info', 'Signed out after 5 minutes of inactivity.');
      nav('/login?expired=1');
    },
  });
  const staySignedIn = async () => {
    try {
      await api.get('/auth/me'); // bumps server-side activity
    } catch {
      /* expired already — interceptor redirects */
    }
    idle.stay();
  };
  const tour = useTour();
  const unreadQ = useQuery({
    queryKey: ['notifications-unread'],
    queryFn: async () => (await api.get('/notifications', { params: { unread: 1, per_page: 1 } })).data.meta.total as number,
    refetchInterval: 60000,
  });
  const unread = unreadQ.data ?? 0;

  const doLogout = async () => {
    try {
      await api.post('/auth/logout');
    } catch {
      /* ignore */
    }
    logout();
    nav('/login');
  };

  const linkCls = ({ isActive }: { isActive: boolean }) =>
    `flex items-center gap-3 rounded-lg px-3 py-2 text-sm ${isActive ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`;

  return (
    <div className="flex min-h-screen">
      <aside className={`${open ? 'block' : 'hidden'} w-64 shrink-0 border-r border-[var(--border)] p-4 md:block`}>
        <p className="px-2 text-sm font-bold text-red-600">PRIMEPOWER MANPOWER</p>
        <p className="mb-4 px-2 text-xs text-[var(--text-muted)]">CRM — Client Management</p>
        {groups.map((g, i) => (
          <div key={i} className="mb-3">
            {g.label && <p className="px-3 pb-1 text-[11px] font-semibold uppercase text-[var(--text-muted)]">{g.label}</p>}
            {g.links
              .filter((l) => !l.roles || hasRole(user, ...(l.roles as ('admin' | 'manager' | 'sales_rep' | 'superadmin')[])))
              .map((l) => (
                <NavLink key={l.to} to={l.to} end={l.to === '/'} className={linkCls} onClick={() => setOpen(false)}>
                  {l.icon}
                  {l.label}
                </NavLink>
              ))}
          </div>
        ))}
        <div className="card mt-6 flex items-center gap-2 p-3">
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-sky-600 text-sm font-bold text-white">
            {(user?.name ?? '?').slice(0, 1)}
          </div>
          <div className="min-w-0 flex-1">
            <p className="truncate text-xs font-semibold">{user?.name}</p>
            <p className="truncate text-[11px] text-[var(--text-muted)]">{user?.email}</p>
          </div>
          <button aria-label="Log out" onClick={doLogout}>
            <LogOut size={16} />
          </button>
        </div>
      </aside>
      <div className="min-w-0 flex-1">
        <header className="sticky top-0 z-10 flex items-center gap-3 border-b border-[var(--border)] bg-[var(--bg-app)]/90 px-4 py-3 backdrop-blur">
          <button className="md:hidden" aria-label="Menu" onClick={() => setOpen((v) => !v)}>
            <Menu size={20} />
          </button>
          <div className="flex flex-1 items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-card)] px-3 py-1.5 text-sm text-[var(--text-muted)]">
            <Search size={16} />
            <input placeholder="Quick search clients, leads, opps…  ( / )" className="w-full bg-transparent outline-none" />
          </div>
          <button aria-label={`Notifications${unread ? `, ${unread} unread` : ''}`} title={unread ? `${unread} unread — see Follow-ups` : 'No unread notifications'} className="relative rounded-lg border border-[var(--border)] p-2">
            <Bell size={18} />
            {unread > 0 && <span className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{unread > 9 ? '9+' : unread}</span>}
          </button>
          <button aria-label="Replay product tour" title="Take the 5-step tour again" onClick={() => tour.replay()} className="rounded-lg border border-[var(--border)] p-2">
            <CircleHelp size={18} />
          </button>
          <ThemeToggle />
        </header>
        <main className="mx-auto max-w-[1400px] p-4 md:p-6">
          <Outlet />
        </main>
      </div>
      {tour.active && (
        <TourCard
          step={tour.step}
          current={tour.current}
          onRoute={tour.onRoute}
          onNext={() => (tour.step === 4 ? tour.finish() : tour.go(tour.step + 1))}
          onBack={() => tour.go(tour.step - 1)}
          onSkip={() => tour.finish()}
          onGoRoute={() => tour.go(tour.step)}
        />
      )}
      {idle.warning && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="alertdialog" aria-modal="true" aria-label="Session expiring">
          <div className="card w-full max-w-sm p-6 text-center">
            <h2 className="text-lg font-semibold">Still there?</h2>
            <p className="mt-1 text-sm text-[var(--text-muted)]">
              You'll be logged out in <strong className="tabular-nums">{idle.secondsLeft}s</strong> after 5 minutes of inactivity.
            </p>
            <button onClick={staySignedIn} className="mt-4 w-full rounded-lg bg-sky-600 py-2 text-sm font-semibold text-white">
              Stay signed in
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
