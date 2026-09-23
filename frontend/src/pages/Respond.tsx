import axios from 'axios';
import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Star } from 'lucide-react';
import logo from '../assets/logo.png';

// Bare instance: no staff JWT attached, no login redirect on errors.
const pub = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1',
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
});

interface PublicSurvey {
  token: string;
  status: string;
  due_at: string | null;
  client_name: string;
  template: { name: string; type: string; questions: { q: string; scale?: number; options?: string[] }[] };
}
interface ExistingResponse {
  score: number;
  comment: string | null;
  responded_at: string;
}

export function RespondPage() {
  const { token = '' } = useParams();
  const [step, setStep] = useState(0);
  const [score, setScore] = useState<number | null>(null);
  const [comment, setComment] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const [done, setDone] = useState(false);
  const [editing, setEditing] = useState(false);

  const surveyQ = useQuery({
    queryKey: ['public-survey', token],
    queryFn: async () => {
      try {
        const r = await pub.get(`/s/${token}`);
        return r.data.data as { survey: PublicSurvey; existing_response: ExistingResponse | null };
      } catch (e: unknown) {
        if (axios.isAxiosError(e)) {
          if (e.response?.status === 404) throw new Error('This survey link is invalid.');
        }
        throw new Error('Could not load this survey. Check your connection and try again.');
      }
    },
    retry: false,
  });

  const submit = async () => {
    if (score === null) {
      setErr('Pick a score first.');
      return;
    }
    setBusy(true);
    setErr('');
    try {
      if (editing) await pub.put(`/s/${token}/respond`, { score, comment: comment || undefined });
      else await pub.post(`/s/${token}/respond`, { score, comment: comment || undefined });
      setDone(true);
    } catch (e: unknown) {
      if (axios.isAxiosError(e)) {
        const s = e.response?.status;
        if (s === 409) setErr('This survey was already answered. You can edit within 24 hours of your first response.');
        else if (s === 410) setErr('This survey has expired (or the 24-hour edit window closed).');
        else if (s === 422) setErr('Please pick a score between 0 and 10.');
        else setErr('Something went wrong sending your response. Please try again.');
      } else setErr('Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto mt-10 w-full max-w-md px-4">
      <img src={logo} alt="Primepower" className="mb-2 h-10 w-auto rounded-md bg-white p-0.5" />
      <h1 className="text-xl font-bold">Client survey</h1>

      {surveyQ.isLoading && <p className="mt-4 text-sm text-[var(--text-muted)]">Loading your survey…</p>}
      {surveyQ.isError && (
        <div className="card mt-4 p-6 text-sm">
          {(surveyQ.error as Error).message}{' '}
          <button className="text-sky-600 underline" onClick={() => surveyQ.refetch()}>Retry</button>
        </div>
      )}

      {surveyQ.data && !done && (
        <SurveyForm
          survey={surveyQ.data.survey}
          existing={surveyQ.data.existing_response}
          step={step}
          setStep={setStep}
          score={score}
          setScore={setScore}
          comment={comment}
          setComment={setComment}
          err={err}
          busy={busy}
          editing={editing}
          setEditing={setEditing}
          onSubmit={submit}
        />
      )}

      {done && (
        <div className="card mt-4 p-8 text-center">
          
          <h2 className="mt-2 text-lg font-semibold">Salamat! Response recorded.</h2>
          <p className="mt-1 text-sm text-[var(--text-muted)]">Your feedback helps Primepower serve you better.</p>
        </div>
      )}
    </div>
  );
}

function SurveyForm(props: {
  survey: PublicSurvey;
  existing: ExistingResponse | null;
  step: number;
  setStep: (n: number) => void;
  score: number | null;
  setScore: (n: number) => void;
  comment: string;
  setComment: (s: string) => void;
  err: string;
  busy: boolean;
  editing: boolean;
  setEditing: (b: boolean) => void;
  onSubmit: () => void;
}) {
  const { survey, existing, step, setStep, score, setScore, comment, setComment, err, busy, editing, setEditing, onSubmit } = props;
  const max = survey.template.type === 'csat' ? 5 : 10;
  const min = survey.template.type === 'csat' ? 1 : 0;

  if (survey.status === 'expired') {
    return <div className="card mt-4 p-6 text-sm">This survey has expired. Thank you for your time — your account manager can send a fresh one.</div>;
  }

  if (existing && !editing && step === 0) {
    return (
      <div className="card mt-4 p-6 text-sm">
        <p>You already answered with <strong><Star size={13} className="mr-0.5 inline" />{existing.score}</strong>{existing.comment ? ` — “${existing.comment}”` : ''}.</p>
        <button onClick={() => { setScore(existing.score); setComment(existing.comment ?? ''); setEditing(true); }} className="mt-3 rounded-lg border border-[var(--border)] px-4 py-2 text-sm">
          Edit my response (within 24 hours)
        </button>
      </div>
    );
  }

  return (
    <div className="card mt-4 p-6">
      <p className="text-sm text-[var(--text-muted)]">{survey.client_name} · {survey.template.name}</p>
      <div className="mt-2 flex gap-1" aria-label={`Step ${step + 1} of 3`}>
        {[0, 1, 2].map((s) => <span key={s} className={`h-1.5 flex-1 rounded ${s <= step ? 'bg-sky-500' : 'bg-slate-200 dark:bg-slate-700'}`} />)}
      </div>

      {step === 0 && (
        <>
          <h2 className="mt-3 font-semibold">{survey.template.questions[0]?.q ?? 'How would you rate our service?'}</h2>
          <div className="mt-3 flex flex-wrap gap-2">
            {Array.from({ length: max - min + 1 }, (_, i) => min + i).map((s) => (
              <button key={s} onClick={() => setScore(s)}
                className={`flex h-10 w-10 items-center justify-center rounded-lg border text-sm font-semibold ${score === s ? 'border-sky-600 bg-sky-600 text-white' : 'border-[var(--border)]'}`}>
                {s}
              </button>
            ))}
          </div>
          <button disabled={score === null} onClick={() => setStep(1)} className="mt-4 w-full rounded-lg bg-sky-600 py-2 text-sm font-semibold text-white disabled:opacity-50">Next: tell us why →</button>
        </>
      )}

      {step === 1 && (
        <>
          <h2 className="mt-3 font-semibold">What stood out? <span className="font-normal text-[var(--text-muted)]">(optional)</span></h2>
          <textarea value={comment} onChange={(e) => setComment(e.target.value)} rows={3} placeholder="e.g. Mabilis ang deployment, salamat!" className="mt-2 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" />
          <div className="mt-4 flex gap-2">
            <button onClick={() => setStep(0)} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">← Back</button>
            <button onClick={() => setStep(2)} className="flex-1 rounded-lg bg-sky-600 py-2 text-sm font-semibold text-white">Review →</button>
          </div>
        </>
      )}

      {step === 2 && (
        <>
          <h2 className="mt-3 font-semibold">Review</h2>
          <p className="mt-1 text-sm">Score: <strong><Star size={13} className="mr-0.5 inline" />{score}</strong></p>
          {comment && <p className="mt-1 text-sm">“{comment}”</p>}
          {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
          <div className="mt-4 flex gap-2">
            <button onClick={() => setStep(1)} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">← Back</button>
            <button disabled={busy} onClick={onSubmit} className="flex-1 rounded-lg bg-sky-600 py-2 text-sm font-semibold text-white disabled:opacity-50">
              {busy ? 'Sending…' : editing ? 'Update response' : 'Submit response'}
            </button>
          </div>
        </>
      )}
    </div>
  );
}
