import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { KpiCard } from '../components/ui/KpiCard';
import { StatusBadge } from '../components/ui/StatusBadge';

interface Client {
  id: string;
  name: string;
  industry: string | null;
  address_city: string | null;
  status: string;
  contact_phone: string | null;
}

interface Contact {
  id: string;
  client_id: string;
  client_name?: string | null;
  full_name: string;
  position: string | null;
  email: string | null;
  phone: string | null;
  is_primary: boolean;
}

interface ConvertedLead {
  id: string;
  company_name: string;
  contact_name: string;
  converted_client_id: string | null;
}

interface Invoice {
  id: string;
  balance_centavos: number;
  status: string;
}

type View = 'clients' | 'people' | 'won';

/** Clients hub page (specs/04 hub): KPIs + one filterable table with three views. */
export function ClientsPage() {
  const [view, setView] = useState<View>('clients');
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');

  const clientsQ = useQuery({
    queryKey: ['clients', q, status],
    queryFn: async () => (await api.get('/clients', { params: { q: q || undefined, status: status || undefined, per_page: 50 } })).data,
  });
  const totalsQ = useQuery({
    queryKey: ['clients', 'totals'],
    queryFn: async () => (await api.get('/clients', { params: { per_page: 100 } })).data.data as Client[],
  });
  const collectQ = useQuery({
    queryKey: ['invoices', 'collectible'],
    queryFn: async () => (await api.get('/invoices', { params: { per_page: 100 } })).data.data as Invoice[],
  });
  const peopleQ = useQuery({
    queryKey: ['contacts', q],
    queryFn: async () => (await api.get('/contacts', { params: { q: q || undefined, per_page: 50 } })).data,
    enabled: view === 'people',
  });
  const wonQ = useQuery({
    queryKey: ['leads', 'converted'],
    queryFn: async () => (await api.get('/leads', { params: { status: 'converted', per_page: 50 } })).data,
    enabled: view === 'won',
  });

  const totals: Client[] = totalsQ.data ?? [];
  const active = totals.filter((c) => c.status === 'active').length;
  const prospects = totals.filter((c) => c.status === 'prospect').length;
  const collectible = (collectQ.data ?? []).reduce((a, i) => a + (i.balance_centavos > 0 ? i.balance_centavos : 0), 0);

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Clients</h1>
        <p className="text-sm text-[var(--text-muted)]">
          Everyone you've won over — every client, one table. Per-client staffing lives under{' '}
          <Link to="/pipeline/staffing" className="text-sky-700 hover:underline dark:text-sky-300">Opportunity Pipeline → Deployed Staff</Link>.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <KpiCard label="Active clients" value={totalsQ.isLoading ? '…' : String(active)} sub="doing business with you now" />
        <KpiCard label="Prospects" value={totalsQ.isLoading ? '…' : String(prospects)} sub="not yet active" />
        <KpiCard label="Collectible now" value={collectQ.isLoading ? '…' : formatPHP(collectible)} sub="open balances across clients" />
      </div>

      <div className="flex flex-wrap gap-1.5" role="group" aria-label="Views">
        {([['clients', 'All clients'], ['people', 'People'], ['won', 'Recently won over']] as [View, string][]).map(([v, label]) => (
          <button key={v} onClick={() => setView(v)} aria-pressed={view === v}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${view === v ? 'bg-sky-600 text-white' : 'border border-[var(--border)]'}`}>
            {label}
          </button>
        ))}
      </div>

      <div className="flex gap-2">
        <input
          value={q} onChange={(e) => setQ(e.target.value)}
          placeholder={view === 'people' ? 'Search person, position, or company…' : 'Search name, city, email…'}
          className="card flex-1 px-3 py-2 text-sm outline-none" />
        {view === 'clients' && (
          <select value={status} onChange={(e) => setStatus(e.target.value)} className="card px-3 py-2 text-sm" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="prospect">Prospect</option>
          </select>
        )}
      </div>

      {view === 'clients' && (
        clientsQ.isLoading ? (
          <p className="text-sm text-[var(--text-muted)]">Loading clients…</p>
        ) : clientsQ.isError ? (
          <div className="card p-6 text-sm">Couldn't load clients. <button className="text-sky-600 underline" onClick={() => clientsQ.refetch()}>Retry</button></div>
        ) : (
          <DataTable<Client>
            rows={clientsQ.data?.data ?? []}
            columns={[
              { key: 'n', header: 'Client', render: (r) => <Link to={`/clients/${r.id}`} className="font-medium text-sky-700 dark:text-sky-300">{r.name}</Link> },
              { key: 'i', header: 'Industry', render: (r) => r.industry ?? '—' },
              { key: 'c', header: 'City', render: (r) => r.address_city ?? '—' },
              { key: 's', header: 'Status', render: (r) => <StatusBadge value={r.status} /> },
              { key: 'st', header: 'Staffing', render: (r) => <Link to={`/pipeline/staffing?client=${r.id}`} className="text-xs text-sky-700 hover:underline dark:text-sky-300">Deployed staff →</Link> },
            ]}
            empty={<EmptyState title="No clients yet" hint="Convert a qualified lead to create your first client profile." action={<Link to="/leads" className="mt-2 inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Find a lead to convert →</Link>} />}
          />
        )
      )}

      {view === 'people' && (
        peopleQ.isLoading ? (
          <p className="text-sm text-[var(--text-muted)]">Loading people…</p>
        ) : peopleQ.isError ? (
          <div className="card p-6 text-sm">Couldn't load people. <button className="text-sky-600 underline" onClick={() => peopleQ.refetch()}>Retry</button></div>
        ) : (
          <DataTable<Contact>
            rows={peopleQ.data?.data ?? []}
            columns={[
              { key: 'n', header: 'Person', render: (r) => <span className="font-medium">{r.full_name} {r.is_primary && <span className="text-xs text-sky-600">(primary)</span>}</span> },
              { key: 'c', header: 'Client', render: (r) => <Link to={`/clients/${r.client_id}`} className="text-sky-700 hover:underline dark:text-sky-300">{r.client_name ?? `#${r.client_id}`}</Link> },
              { key: 'p', header: 'Position', render: (r) => r.position ?? '—' },
              { key: 'ct', header: 'Reach them', render: (r) => <span className="text-xs">{r.phone ?? r.email ?? '—'}</span> },
            ]}
            empty={<EmptyState title="Nobody here yet" hint="Contacts you add on a client profile will show up in this directory." />}
          />
        )
      )}

      {view === 'won' && (
        wonQ.isLoading ? (
          <p className="text-sm text-[var(--text-muted)]">Loading conversions…</p>
        ) : wonQ.isError ? (
          <div className="card p-6 text-sm">Couldn't load conversions. <button className="text-sky-600 underline" onClick={() => wonQ.refetch()}>Retry</button></div>
        ) : (
          <DataTable<ConvertedLead>
            rows={wonQ.data?.data ?? []}
            columns={[
              { key: 'co', header: 'Was', render: (r) => <Link to={`/leads/${r.id}`} className="text-sky-700 hover:underline dark:text-sky-300">{r.company_name}</Link> },
              { key: 'ct', header: 'Contact', render: (r) => r.contact_name },
              { key: 'now', header: 'Now', render: (r) => r.converted_client_id
                ? <Link to={`/clients/${r.converted_client_id}`} className="font-medium text-sky-700 hover:underline dark:text-sky-300">Client #{r.converted_client_id} →</Link>
                : '—' },
            ]}
            empty={<EmptyState title="No conversions yet" hint="Qualified leads you convert will be logged here." action={<Link to="/leads" className="mt-2 inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Work the queue →</Link>} />}
          />
        )
      )}
    </div>
  );
}
