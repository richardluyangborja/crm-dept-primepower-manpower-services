import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';

/** String-list master data from /settings with a fallback default. */
export function useSettingsList(key: string, fallback: string[]): string[] {
  const q = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get('/settings')).data.data as Record<string, unknown>,
    staleTime: 60000,
  });
  const v = q.data?.[key];
  if (Array.isArray(v) && v.every((x): x is string => typeof x === 'string') && v.length > 0) return v;
  return fallback;
}
