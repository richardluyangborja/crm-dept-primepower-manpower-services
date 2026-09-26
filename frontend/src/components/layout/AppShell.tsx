import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { LayoutDashboard, LogOut, Menu, Search, Users, KanbanSquare, MessagesSquare, Star, BellRing, BarChart3, Settings, CircleHelp, Briefcase, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../lib/apiClient';
import { hasRole, useSession } from '../../store/session';
import { ThemeToggle } from '../ui/ThemeToggle';
import { NotificationPanel } from '../crm/NotificationPanel';
import { ConfirmDialog } from '../ui/ConfirmDialog';
import logo from '../../assets/logo.png';
import { useToast } from '../ui/Toaster';
import { useIdleTimer } from '../../hooks/useIdleTimer';
import { TourCard, useTour } from '../ui/Tour';

interface NavChild {
  to: string;
  label: string;
  roles?: string[];
}
interface NavLinkItem {
  to: string;
  label: string;
  icon: React.ReactNode;
  roles?: string[];
  children?: NavChild[];
  storageKey?: string;
}

const groups: { label: string; links: NavLinkItem[] }[] = [
  { label: '', links: [{ to: '/', label: 'Dashboard', icon: <LayoutDashboard size={18} /> }] },
  {
    label: 'Sales',
    links: [
      {
        to: '/leads',
        label: 'Lead & Client Tracking',
        icon: <Users size={18} />,
        storageKey: 'crm.nav.leadclient',
        children: [
          { to: '/leads', label: 'Leads' },
          { to: '/clients', label: 'Clients' },
        ],
      },
      {
        to: '/pipeline',
        label: 'Opportunity Pipeline',
        icon: <KanbanSquare size={18} />,
        storageKey: 'crm.nav.pipeline',
        children: [
          { to: '/pipeline', label: 'Visualization Board' },
          { to: '/pipeline/finance', label: 'Billing' },
          { to: '/pipeline/staffing', label: 'Deployed Staff' },
          { to: '/pipeline/contracts', label: 'Contracts' },
        ],
      },
      { to: '/followups', label: 'Follow-ups', icon: <BellRing size={18} /> },
      {
        to: '/workforce',
        label: 'Workforce',
        icon: <Briefcase size={18} />,
        storageKey: 'crm.nav.workforce',
        children: [
          { to: '/workforce', label: 'Directory' },
          { to: '/workforce/attendance', label: 'Attendance' },
          { to: '/workforce/leave', label: 'Leave' },
          { to: '/workforce/performance', label: 'Performance' },
        ],
      },
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
  { label: 'System', links: [{ to: '/settings', label: 'Settings', icon: <Settings size={18} /> }] },
];

export function AppShell() {
  const { user, logout } = useSession();
  const [open, setOpen] = useState(false);
  const [confirmLogout, setConfirmLogout] = useState(false);
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
    setConfirmLogout(true);
  };

  const runLogout = async () => {
    setConfirmLogout(false);
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
  const location = useLocation();
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>(() => {
    try {
      return JSON.parse(localStorage.getItem('crm.nav.collapsed') ?? '{}');
    } catch {
      return {};
    }
  });
  const toggleGroup = (key: string) =>
    setCollapsed((prev) => {
      const next = { ...prev, [key]: !prev[key] };
      try {
        localStorage.setItem('crm.nav.collapsed', JSON.stringify(next));
      } catch {
        /* storage full/blocked — collapse state just won't persist */
      }
      return next;
    });
  const childActive = (children?: NavChild[]) =>
    !!children?.some((c) => location.pathname === c.to || location.pathname.startsWith(c.to + '/'));

  return (
    <div className="flex min-h-screen">
      <aside className={`${open ? 'block' : 'hidden'} w-64 shrink-0 self-start border-r border-[var(--border)] p-4 md:sticky md:top-0 md:block md:h-screen md:overflow-y-auto`}>
        <div className="mb-4 flex items-center gap-2 px-2">
          <img src={logo} alt="Primepower" className="h-10 w-auto" />
          <div>
            <p className="text-sm font-bold leading-tight">Primepower</p>
            <p className="text-[11px] text-[var(--text-muted)]">CRM</p>
          </div>
        </div>
        {groups.map((g, i) => (
          <div key={i} className="mb-3">
            {g.label && <p className="px-3 pb-1 text-[11px] font-semibold uppercase text-[var(--text-muted)]">{g.label}</p>}
            {g.links
              .filter((l) => !l.roles || hasRole(user, ...(l.roles as ('admin' | 'manager' | 'sales_rep' | 'superadmin')[])))
              .map((l) => {
                if (!l.children) {
                  return (
                    <NavLink key={l.to} to={l.to} end={l.to === '/'} className={linkCls} onClick={() => setOpen(false)}>
                      {l.icon}
                      {l.label}
                    </NavLink>
                  );
                }
                const isOpen = childActive(l.children) || !collapsed[l.storageKey ?? l.to];
                const kids = l.children.filter(
                  (c) => !c.roles || hasRole(user, ...(c.roles as ('admin' | 'manager' | 'sales_rep' | 'superadmin')[])),
                );
                return (
                  <div key={l.to}>
                    <div className="flex items-center gap-1">
                      <NavLink
                        to={l.to}
                        end
                        onClick={() => setOpen(false)}
                        className={({ isActive }: { isActive: boolean }) =>
                          // Only the active child gets styled — never the parent alongside it.
                          linkCls({ isActive: isActive && !childActive(l.children) })
                        }
                      >
                        <span className="flex items-center gap-3">
                          {l.icon}
                          {l.label}
                        </span>
                      </NavLink>
                      <button
                        aria-label={`${isOpen ? 'Collapse' : 'Expand'} ${l.label} submenu`}
                        aria-expanded={isOpen}
                        onClick={() => toggleGroup(l.storageKey ?? l.to)}
                        className="rounded p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800"
                      >
                        <ChevronDown size={16} className={`transition-transform ${isOpen ? '' : '-rotate-90'}`} />
                      </button>
                    </div>
                    {isOpen && kids.length > 0 && (
                      <div className="ml-9 flex flex-col gap-0.5 border-l border-[var(--border)] pl-2">
                        {kids.map((c) => (
                          <NavLink
                            key={c.to}
                            to={c.to}
                            end
                            onClick={() => setOpen(false)}
                            className={({ isActive }: { isActive: boolean }) =>
                              `rounded-lg px-3 py-1.5 text-[13px] ${isActive ? 'bg-sky-100 font-medium text-sky-900 dark:bg-sky-900/40 dark:text-sky-100' : 'text-[var(--text-muted)] hover:bg-slate-100 hover:text-inherit dark:hover:bg-slate-800'}`
                            }
                          >
                            {c.label}
                          </NavLink>
                        ))}
                      </div>
                    )}
                  </div>
                );
              })}
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
      <ConfirmDialog
        open={confirmLogout}
        tone="info"
        title="Sign out?"
        body="You'll need your login again to get back in. Unsaved form work on this page will be lost."
        confirmLabel="Sign out"
        onCancel={() => setConfirmLogout(false)}
        onConfirm={() => void runLogout()}
      />
      <div className="min-w-0 flex-1">
        <header className="sticky top-0 z-10 flex items-center gap-3 border-b border-[var(--border)] bg-[var(--bg-app)]/90 px-4 py-3 backdrop-blur">
          <button className="md:hidden" aria-label="Menu" onClick={() => setOpen((v) => !v)}>
            <Menu size={20} />
          </button>
          <div className="flex flex-1 items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-card)] px-3 py-1.5 text-sm text-[var(--text-muted)]">
            <Search size={16} />
            <input placeholder="Quick search clients, leads, opps…  ( / )" className="w-full bg-transparent outline-none" />
          </div>
          <NotificationPanel unread={unread} />
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
