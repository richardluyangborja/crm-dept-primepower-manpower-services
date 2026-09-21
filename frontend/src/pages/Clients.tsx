import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';

interface Client {
  id: number;
  name: string;
  industry: string | null;
  address_city: string | null;
  status: string;
  contact_phone: string | null;
}

interface Contact {
  id: number;
  client_id: number;
  client_name?: string | null;
  full_name: string;
  position: string | null;
  email: string | null;
  phone: string | null;
  is_primary: boolean;
}

interface ConvertedLead {
  id: number;
  company_name: string;
  contact_name: string;
  converted_client_id: number | null;
}

/** Clients hub page (specs/04 hub): directory + people + conversion log, sectionized. */
export function ClientsPage() {
  const [q, setQ] = useState('');
  const [pq, setPq] = useState('');

  const clientsQ = useQuery({
    queryKey: ['clients', q],
    queryFn: async () => (await api.get('/clients', { params: { q: q || undefined, per_page: 50 } })).data,
  });
  const peopleQ = useQuery({
    queryKey: ['contacts', pq],
    queryFn: async () => (await api.get('/contacts', { params: { q: pq || undefined, per_page: 50 } })).data,
  });
  const wonQ = useQuery({
    queryKey: ['leads', 'converted'],
    queryFn: async () => (await api.get('/leads', { params: { status: 'converted', per_page: 50 } })).data,
  });

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Clients</h1>
        <p className="text-sm text-[var(--text-muted)]">
          Everyone you've won over. Per-client staffing lives under{' '}
          <Link to="/pipeline/staffing" className="text-sky-700 hover:underline dark:text-sky-300">Opportunity Pipeline → Deployed Staff</Link>.
        </p>
      </div>
      <nav aria-label="Page sections" className="flex flex-wrap gap-1.5">
        {[
          { id: 'directory', label: 'All clients' },
          { id: 'people', label: 'People' },
          { id: 'won-over', label: 'Recently won over' },
        ].map((s) => (
          <a key={s.id} href={`#${s.id}`} className="rounded-lg border border-[var(--border)] px-3 py-1.5 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
            {s.label}
          </a>
        ))}
      </nav>

      <section id="directory" aria-label="All clients" className="flex scroll-mt-24 flex-col gap-2">
        <h2 className="text-base font-semibold">All clients</h2>
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name, city, email…" className="card px-3 py-2 text-sm outline-none" />
        {clientsQ.isLoading ? (
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
        )}
      </section>

      <section id="people" aria-label="People" className="flex scroll-mt-24 flex-col gap-2">
        <h2 className="text-base font-semibold">People</h2>
        <p className="text-sm text-[var(--text-muted)]">Who you know at each account — across all clients, primary contacts first.</p>
        <input value={pq} onChange={(e) => setPq(e.target.value)} placeholder="Search person, position, or company…" className="card px-3 py-2 text-sm outline-none" />
        {peopleQ.isLoading ? (
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
        )}
      </section>

      <section id="won-over" aria-label="Recently won over" className="flex scroll-mt-24 flex-col gap-2">
        <h2 className="text-base font-semibold">Recently won over</h2>
        <p className="text-sm text-[var(--text-muted)]">Converted inquiries and the client profiles they became.</p>
        {wonQ.isLoading ? (
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
        )}
      </section>
    </div>
  );
}
