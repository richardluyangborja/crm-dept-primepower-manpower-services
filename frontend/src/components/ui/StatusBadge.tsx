const palette: Record<string, string> = {
  active: 'bg-green-100 text-green-800',
  prospect: 'bg-sky-100 text-sky-800',
  open: 'bg-sky-100 text-sky-800',
  overdue: 'bg-red-100 text-red-800',
  high: 'bg-red-100 text-red-800',
  medium: 'bg-amber-100 text-amber-800',
  low: 'bg-slate-100 text-slate-700',
  won: 'bg-green-100 text-green-800',
  lost: 'bg-slate-200 text-slate-700',
};

export function StatusBadge({ value }: { value: string }) {
  return (
    <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${palette[value] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'}`}>
      {value}
    </span>
  );
}
