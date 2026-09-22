/** Consistent section heading: title + optional source tag + hint line. */
export function SectionHead({ title, tag, hint }: { title: string; tag?: string; hint?: string }) {
  return (
    <div>
      <h2 className="text-base font-semibold">
        {title}
        {tag && <span className="ml-2 align-middle text-xs font-normal text-[var(--text-muted)]">{tag}</span>}
      </h2>
      {hint && <p className="mt-0.5 text-sm text-[var(--text-muted)]">{hint}</p>}
    </div>
  );
}
