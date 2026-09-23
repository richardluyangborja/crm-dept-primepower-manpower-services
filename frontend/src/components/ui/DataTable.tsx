export interface Column<T> {
  key: string;
  header: string;
  render: (row: T) => React.ReactNode;
}

export interface Pagination {
  page: number;
  perPage: number;
  total: number;
  onPage: (page: number) => void;
}

/** Minimal reusable table. Agents: extend (sorting/pagination), never fork per module. */
export function DataTable<T extends { id: number | string }>({
  columns,
  rows,
  empty,
  pagination,
}: {
  columns: Column<T>[];
  rows: T[];
  empty: React.ReactNode;
  pagination?: Pagination;
}) {
  if (rows.length === 0) return <>{empty}</>;
  const pages = pagination ? Math.max(1, Math.ceil(pagination.total / pagination.perPage)) : 1;
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
      {pagination && pages > 1 && (
        <div className="flex items-center justify-between border-t border-[var(--border)] px-4 py-2 text-xs text-[var(--text-muted)]">
          <span className="tabular-nums">
            Page {pagination.page} of {pages} · {pagination.total} total
          </span>
          <span className="flex gap-1.5">
            <button
              disabled={pagination.page <= 1}
              onClick={() => pagination.onPage(pagination.page - 1)}
              className="rounded-lg border border-[var(--border)] px-2.5 py-1 font-medium text-inherit disabled:opacity-40"
            >
              ← Prev
            </button>
            <button
              disabled={pagination.page >= pages}
              onClick={() => pagination.onPage(pagination.page + 1)}
              className="rounded-lg border border-[var(--border)] px-2.5 py-1 font-medium text-inherit disabled:opacity-40"
            >
              Next →
            </button>
          </span>
        </div>
      )}
    </div>
  );
}
