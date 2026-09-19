export interface Column<T> {
  key: string;
  header: string;
  render: (row: T) => React.ReactNode;
}

/** Minimal reusable table. Agents: extend (sorting/pagination), never fork per module. */
export function DataTable<T extends { id: number | string }>({
  columns,
  rows,
  empty,
}: {
  columns: Column<T>[];
  rows: T[];
  empty: React.ReactNode;
}) {
  if (rows.length === 0) return <>{empty}</>;
  return (
    <div className="card overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-[var(--border)] text-left text-xs uppercase text-[var(--text-muted)]">
            {columns.map((c) => (
              <th key={c.key} className="px-4 py-3 font-medium">
                {c.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.id} className="border-b border-[var(--border)] last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/50">
              {columns.map((c) => (
                <td key={c.key} className="px-4 py-3">
                  {c.render(r)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
