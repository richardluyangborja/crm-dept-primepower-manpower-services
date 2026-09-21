import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import api from '../../lib/apiClient';

const STORAGE_KEY = 'crm.tour_seen';

interface Step {
  route: string;
  title: string;
  body: string;
}

// The 5-step main-process journey (specs/18 §2): where work comes from,
// who it's for, how it closes, and how it gets followed up and measured.
const STEPS: Step[] = [
  {
    route: '/',
    title: '1/5 · Start on the Dashboard',
    body: 'This is the morning view: weighted forecast, at-risk clients, and your next best actions. Everything here links somewhere — follow one now.',
  },
  {
    route: '/leads',
    title: '2/5 · Capture and convert',
    body: 'Leads live here: work the Needs-a-response queue, then browse all inquiries. Converted clients move to Clients — open one for the full profile: profile, deals & orders, conversations, and billing.',
  },
  {
    route: '/pipeline',
    title: '3/5 · Win the deal',
    body: 'Drag deals across the kanban. Marking one won creates Job Order JO-2026-XXXX automatically — staffing starts, and the client timeline tells the story.',
  },
  {
    route: '/followups',
    title: '4/5 · Never drop the ball',
    body: 'Every promise becomes a reminder. Overdue items escalate after 72 hours, and the calendar shows the whole book.',
  },
  {
    route: '/reports',
    title: '5/5 · Prove it with reports',
    body: 'Weekly and monthly packs with the executive narrative, CSV exports, and print-to-PDF. This is the decision-support output of the research title.',
  },
];

async function persistSeen() {
  try {
    await api.put('/me/preferences', { tour_seen: true });
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
      .then((r: { data?: { data?: { tour_seen?: boolean } } }) => {
        if (!cancelled && !r.data?.data?.tour_seen) setActive(true);
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

  // If the user navigates away mid-tour, keep the card but don't force routes.
  const current = STEPS[step];
  const onRoute = location.pathname === current.route;

  return { active, step, current, onRoute, go, finish, replay, setActive };
}

export function TourCard({
  step,
  current,
  onRoute,
  onNext,
  onBack,
  onSkip,
  onGoRoute,
}: {
  step: number;
  current: Step;
  onRoute: boolean;
  onNext: () => void;
  onBack: () => void;
  onSkip: () => void;
  onGoRoute: () => void;
}) {
  return (
    <div className="fixed bottom-4 left-4 z-50 w-80 max-w-[calc(100vw-2rem)] card border-l-4 border-l-sky-500 p-4 shadow-lg" role="dialog" aria-label="Product tour">
      <p className="font-semibold">{current.title}</p>
      <p className="mt-1 text-sm text-[var(--text-muted)]">{current.body}</p>
      {!onRoute && (
        <button onClick={onGoRoute} className="mt-2 rounded-lg bg-sky-600 px-3 py-1.5 text-xs text-white">
          Take me there →
        </button>
      )}
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
