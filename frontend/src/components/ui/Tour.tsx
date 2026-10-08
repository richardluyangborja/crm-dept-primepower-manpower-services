import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import api from '../../lib/apiClient';

const STORAGE_KEY = 'crm.tour_seen_v2';
const PREF_KEY = 'tour_seen_v2';

interface Step {
  route: string;
  title: string;
  body: string;
  /** Optional in-context action, e.g. jumping straight to the board. */
  action?: { label: string; route: string };
}

// The main-flow journey: where work comes from, who it's for, how it
// closes, and how it gets followed up, measured, and reported. Each step
// explains the why and offers the next move.
const STEPS: Step[] = [
  {
    route: '/',
    title: 'Start on the Dashboard',
    body: 'Your morning view: weighted forecast, at-risk clients, and next best actions. Every number links somewhere — open a lead or deal to work it. When you are done looking around, capture the next inquiry.',
    action: { label: 'Capture a lead →', route: '/leads' },
  },
  {
    route: '/leads',
    title: 'Leads become clients',
    body: 'Work the Needs-a-response queue first. Scores compute themselves — any valid email counts. Positions are rank-and-file only (this agency deploys no managers). Qualify a lead, open a deal, and winning converts it into a client with its history carried over.',
    action: { label: 'See clients →', route: '/clients' },
  },
  {
    route: '/pipeline',
    title: 'Win the deal on the board',
    body: 'Drag cards between stages, or click one for the detail popup: terms, billing math, and the full stage-history timeline. Sign the contract when terms are agreed — winning creates the job order and first invoice automatically. Skipped stages ask for a reason so history stays honest.',
    action: { label: 'Check follow-ups →', route: '/followups' },
  },
  {
    route: '/followups',
    title: 'Never drop the ball',
    body: 'The calendar is the default view; switch to List view for the queue. Clear overdue first — anything overdue 72 hours is flagged to your manager (see the ⓘ alert up top). Snoozing pauses the countdown; finishing resolves it.',
    action: { label: 'Send a survey →', route: '/surveys' },
  },
  {
    route: '/surveys',
    title: 'Measure satisfaction',
    body: 'Monthly pulses and deployment check-ins feed the satisfaction chart on the Dashboard and the management report. Low scores flag the client automatically — re-engage before renewal comes up.',
    action: { label: 'Check the workforce →', route: '/workforce' },
  },
  {
    route: '/workforce',
    title: 'Know who delivers',
    body: "The directory lists everyone on the team. Click Leave, Attendance, or Performance on any row to open that person's record — deployment quality starts with the crew.",
    action: { label: 'Prove it with reports →', route: '/reports' },
  },
  {
    route: '/reports',
    title: 'Report to management',
    body: 'Weekly and monthly packs: executive narrative, 12-month sales and satisfaction charts, risks, and CSV exports. Print / PDF produces a clean board-ready report — no buttons, just the story. Everything else (users, OTP, backups, schedules) lives in Settings.',
  },
];

async function persistSeen() {
  try {
    await api.put('/me/preferences', { [PREF_KEY]: true });
  } catch {
    // Backend persistence is best-effort; local flag still stops the tour.
  }
}

export function useTour() {
  const [active, setActive] = useState(false);
  const [step, setStep] = useState(0);
  const location = useLocation();
  const nav = useNavigate();

  // Auto-start once per user (backend flag wins, local flag is the fast path).
  useEffect(() => {
    if (localStorage.getItem(STORAGE_KEY)) return;
    let cancelled = false;
    api
      .get('/me/preferences')
      .then((r: { data?: { data?: Record<string, boolean> } }) => {
        if (!cancelled && !r.data?.data?.[PREF_KEY]) setActive(true);
        else localStorage.setItem(STORAGE_KEY, '1');
      })
      .catch(() => {
        if (!cancelled) setActive(true);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const finish = () => {
    localStorage.setItem(STORAGE_KEY, '1');
    void persistSeen();
    setActive(false);
    setStep(0);
  };

  const replay = () => {
    setStep(0);
    setActive(true);
  };

  const go = (i: number) => {
    setStep(i);
    nav(STEPS[i].route);
  };

  const next = () => {
    if (step >= STEPS.length - 1) finish();
    else go(step + 1);
  };

  const act = (route: string) => {
    // Actions move the story forward: follow the jump with the next step.
    setStep((s) => Math.min(s + 1, STEPS.length - 1));
    nav(route);
  };

  // If the user navigates away mid-tour, keep the card but don't force routes.
  const current = STEPS[step];
  const onRoute = location.pathname === current.route;

  return { active, step, total: STEPS.length, current, onRoute, go, next, act, finish, replay, setActive };
}

export function TourCard({
  step,
  current,
  onRoute,
  onNext,
  onBack,
  onSkip,
  onGoRoute,
  onAction,
}: {
  step: number;
  current: Step;
  onRoute: boolean;
  onNext: () => void;
  onBack: () => void;
  onSkip: () => void;
  onGoRoute: () => void;
  onAction: (route: string) => void;
}) {
  return (
    <div className="fixed bottom-4 left-4 z-50 w-80 max-w-[calc(100vw-2rem)] card border-l-4 border-l-sky-500 p-4 shadow-lg" role="dialog" aria-label="Product tour">
      <p className="font-semibold">{current.title}</p>
      <p className="mt-1 text-sm text-[var(--text-muted)]">{current.body}</p>
      <div className="mt-2 flex flex-wrap gap-2">
        {!onRoute && (
          <button onClick={onGoRoute} className="rounded-lg bg-sky-600 px-3 py-1.5 text-xs text-white">
            Take me there →
          </button>
        )}
        {current.action && (
          <button onClick={() => onAction(current.action!.route)} className="rounded-lg border border-sky-600 px-3 py-1.5 text-xs text-sky-700 dark:text-sky-300">
            {current.action.label}
          </button>
        )}
      </div>
      <div className="mt-3 flex items-center justify-between">
        <button onClick={onSkip} className="text-xs text-[var(--text-muted)] underline">
          Skip tour
        </button>
        <div className="flex items-center gap-2">
          <span className="text-xs tabular-nums text-[var(--text-muted)]">{step + 1} / {STEPS.length}</span>
          {step > 0 && (
            <button onClick={onBack} className="rounded-lg border border-[var(--border)] px-3 py-1.5 text-xs">
              Back
            </button>
          )}
          <button onClick={onNext} className="rounded-lg bg-sky-600 px-3 py-1.5 text-xs text-white">
            {step === STEPS.length - 1 ? 'Finish' : 'Next'}
          </button>
        </div>
      </div>
    </div>
  );
}

export const TOUR_STEPS = STEPS;
