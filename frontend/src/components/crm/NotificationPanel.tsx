import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Bell, CheckCheck } from 'lucide-react';
import api from '../../lib/apiClient';
import { useToast } from '../ui/Toaster';
import { apiErr } from './ClientWidgets';

interface Note {
  id: string;
  type: string;
  title: string;
  body: string | null;
  link: string | null;
  read_at: string | null;
  created_at: string;
}

/** Header bell with dropdown inbox: read/unread persists server-side, clear-all included. */
export function NotificationPanel({ unread }: { unread: number }) {
  const [open, setOpen] = useState(false);
  const toast = useToast();
  const qc = useQueryClient();
  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['notifications'] });
    qc.invalidateQueries({ queryKey: ['notifications-unread'] });
  };

  const listQ = useQuery({
    queryKey: ['notifications'],
    queryFn: async () => (await api.get('/notifications', { params: { per_page: 20 } })).data.data as Note[],
    enabled: open,
  });
  const readMut = useMutation({
    mutationFn: async (id: string) => (await api.post(`/notifications/${id}/read`)).data,
    onSuccess: () => refresh(),
    onError: (e) => toast('error', apiErr(e, 'Could not mark as read.')),
  });
  const clearMut = useMutation({
    mutationFn: async () => (await api.post('/notifications/read-all')).data,
    onSuccess: () => {
      toast('success', 'Inbox cleared.');
      refresh();
    },
    onError: (e) => toast('error', apiErr(e, 'Could not clear inbox.')),
  });

  const rows = listQ.data ?? [];
  const rel = (iso: string) => {
    const mins = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const h = Math.floor(mins / 60);
    if (h < 24) return `${h}h ago`;
    return `${Math.floor(h / 24)}d ago`;
  };

  return (
    <div className="relative">
      <button
        aria-label={`Notifications${unread ? `, ${unread} unread` : ''}`}
        aria-expanded={open}
        title={unread ? `${unread} unread` : 'No unread notifications'}
        onClick={() => setOpen((v) => !v)}
        className="relative rounded-lg border border-[var(--border)] p-2"
      >
        <Bell size={18} />
        {unread > 0 && <span className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{unread > 9 ? '9+' : unread}</span>}
      </button>
      {open && (
        <div className="card absolute right-0 z-30 mt-2 flex max-h-96 w-80 flex-col p-0 shadow-lg" role="dialog" aria-label="Notifications">
          <div className="flex items-center justify-between border-b border-[var(--border)] px-3 py-2">
            <p className="text-sm font-semibold">Notifications</p>
            <span className="flex gap-1">
              <button onClick={() => clearMut.mutate()} disabled={unread === 0} title="Mark everything read"
                className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-sky-700 hover:underline disabled:text-[var(--text-muted)] disabled:no-underline dark:text-sky-300">
                <CheckCheck size={13} /> Clear all
              </button>
              <button onClick={() => setOpen(false)} className="rounded px-2 py-1 text-xs text-[var(--text-muted)]" aria-label="Close notifications">✕</button>
            </span>
          </div>
          <div className="overflow-y-auto">
            {listQ.isLoading ? <p className="p-3 text-sm text-[var(--text-muted)]">Loading…</p>
              : listQ.isError ? <p className="p-3 text-sm text-red-600">Couldn't load notifications.</p>
              : rows.length === 0 ? <p className="p-3 text-sm text-[var(--text-muted)]">All caught up — nothing here.</p>
              : (
                <ul className="flex flex-col">
                  {rows.map((n) => (
                    <li key={n.id} className={`border-b border-[var(--border)] px-3 py-2 last:border-0 ${n.read_at ? '' : 'bg-sky-50 dark:bg-sky-950/30'}`}>
                      <div className="flex items-start gap-2">
                        {!n.read_at && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-sky-500" aria-label="Unread" />}
                        <div className="min-w-0 flex-1">
                          {n.link ? (
                            <Link to={n.link} onClick={() => { if (!n.read_at) readMut.mutate(n.id); setOpen(false); }} className="text-sm font-medium text-sky-700 hover:underline dark:text-sky-300">
                              {n.title}
                            </Link>
                          ) : (
                            <p className="text-sm font-medium">{n.title}</p>
                          )}
                          {n.body && <p className="truncate text-xs text-[var(--text-muted)]" title={n.body}>{n.body}</p>}
                          <p className="mt-0.5 text-[11px] text-[var(--text-muted)]">{rel(n.created_at)}{!n.read_at && ' · unread'}</p>
                        </div>
                        {!n.read_at && (
                          <button onClick={() => readMut.mutate(n.id)} className="shrink-0 rounded px-1.5 py-0.5 text-[11px] text-sky-700 hover:underline dark:text-sky-300">
                            Mark read
                          </button>
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
          </div>
        </div>
      )}
    </div>
  );
}
