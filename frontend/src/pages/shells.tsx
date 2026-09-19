import { EmptyState } from '../components/ui/EmptyState';

/** Shell factory for module streams — each agent replaces their shell with the real page. */
function shell(title: string, hint: string, owner: string) {
  return function Shell() {
    return (
      <div className="flex flex-col gap-4">
        <div>
          <h1 className="text-xl font-bold">{title}</h1>
          <p className="text-sm text-[var(--text-muted)]">{hint}</p>
        </div>
        <EmptyState title={`${title} — coming in ${owner}`} hint="Base scaffold only. The owning agent stream builds this page next." />
      </div>
    );
  };
}

export const SurveysPage = shell('Satisfaction & Surveys', 'NPS/CSAT templates and analytics. Step 5 (specs/06).', 'Step 5');
export const CommsPage = shell('Communications', 'Unified timeline and loggers. Step 4 (specs/07).', 'Step 4');
export const ReportsPage = shell('Reports', 'Weekly/monthly packs + AI insights. Step 7 (specs/15).', 'Step 7');
export const SettingsPage = shell('Settings', 'General, appearance, users, security, integrations. Step 6 (specs/09).', 'Step 6');
