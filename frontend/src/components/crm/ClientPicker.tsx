import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import api from '../../lib/apiClient';

/** Client picker synced to `?client=` so Kanban card links deep-link here. */
export function useClientParam() {
  const [params, setParams] = useSearchParams();
  const clientId = params.get('client') ?? '';
  const setClientId = (id: string) => {
    const next = new URLSearchParams(params);
    if (id) next.set('client', id);
    else next.delete('client');
    setParams(next, { replace: true });
  };
  return { clientId, setClientId };
}

export function ClientPicker({ clientId, onChange }: { clientId: string; onChange: (id: string) => void }) {
  const clientsQ = useQuery({
    queryKey: ['clients-mini'],
    queryFn: async () => (await api.get('/clients', { params: { per_page: 100 } })).data.data as { id: number; name: string }[],
  });
  return (
    <select
      value={clientId}
      onChange={(e) => onChange(e.target.value)}
      className="card px-3 py-2 text-sm"
      aria-label="Filter by client"
    >
      <option value="">All clients</option>
      {clientsQ.data?.map((c) => (
        <option key={c.id} value={c.id}>{c.name}</option>
      ))}
    </select>
  );
}
