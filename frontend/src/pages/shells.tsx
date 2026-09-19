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

export const LeadsPage = shell('Leads & Clients', 'Capture, qualify, convert. Owner: Agent A (specs/04).', 'Agent A');
export const PipelinePage = shell('Opportunity Pipeline', 'Kanban, win/loss, forecast. Owner: Agent B (specs/05).', 'Agent B');
export const SurveysPage = shell('Satisfaction & Surveys', 'NPS/CSAT templates and analytics. Owner: Agent C (specs/06).', 'Agent C');
export const CommsPage = shell('Communications', 'Unified timeline and loggers. Owner: Agent D (specs/07).', 'Agent D');
export const FollowupsPage = shell('Follow-ups', 'Reminders, escalation, calendar. Owner: Agent E (specs/08).', 'Agent E');
export const ReportsPage = shell('Reports', 'Weekly/monthly packs + AI insights. Owner: Agent G (specs/15).', 'Agent G');
export const SettingsPage = shell('Settings', 'General, appearance, users, security, integrations. Owner: Agent F (specs/09).', 'Agent F');
