import { AlertTriangle, CheckCircle2, Info } from 'lucide-react';
import { Link } from 'react-router-dom';
import type { ReactNode } from 'react';

/** Explainer strip for detail pages: icon + bold lead + muted body + optional link. */
export function InfoCallout({
  tone = 'info',
  lead,
  children,
  link,
}: {
  tone?: 'info' | 'warn' | 'success';
  lead: string;
  children?: ReactNode;
  link?: { label: string; href: string };
}) {
  const icon =
    tone === 'warn' ? <AlertTriangle size={16} className="mt-0.5 shrink-0 text-amber-600" />
    : tone === 'success' ? <CheckCircle2 size={16} className="mt-0.5 shrink-0 text-green-600" />
    : <Info size={16} className="mt-0.5 shrink-0 text-sky-600" />;
  const border = tone === 'warn' ? 'border-amber-500/40' : tone === 'success' ? 'border-green-500/40' : 'border-sky-500/40';
  return (
    <div className={`card flex gap-2.5 border-l-4 p-3.5 ${border}`}>
      {icon}
      <p className="text-sm">
        <span className="font-semibold">{lead}</span>
        {children && <span className="text-[var(--text-muted)]"> {children}</span>}
        {link && (
          <Link to={link.href} className="ml-1 font-medium text-sky-700 hover:underline dark:text-sky-300">
            {link.label} →
          </Link>
        )}
      </p>
    </div>
  );
}
