import { useState } from 'react';
import api from '../../lib/apiClient';
import { useToast } from '../ui/Toaster';
import { apiErr } from './ClientWidgets';
import { defaultDue } from './StageUpModal';

const TOUCH_TYPES = ['call', 'meeting', 'email', 'site_visit', 'note'];
const OUTCOMES = ['connected', 'no_answer', 'voicemail', 'busy', 'gatekeeper', 'follow_up_needed'];

/** Lead contacted ritual (specs/04): log the first touch + optional follow-up, company-scoped. */
export function LeadTouchPrompt({ companyId, companyName, headcount, onClose, onDone }: {
  companyId: string;
  companyName: string;
  headcount?: number | null;
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const [type, setType] = useState('call');
  const [outcome, setOutcome] = useState('connected');
  const [notes, setNotes] = useState('');
  const [addFollowup, setAddFollowup] = useState(true);
  const [fTitle, setFTitle] = useState(`Follow up with ${companyName}${headcount ? ` — ${headcount} heads` : ''}`);
  const [fDue, setFDue] = useState(defaultDue(3));
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!notes.trim()) { setErr('Add a short note — it becomes the timeline entry.'); return; }
    if (addFollowup && (!fTitle.trim() || !fDue)) { setErr('Follow-up needs a title and a due time.'); return; }
    setBusy(true);
    setErr('');
    try {
      await api.post('/activities', {
        company_id: companyId, type, outcome,
        subject: `First touch — ${companyName}`, body: notes.trim(),
      });
      if (addFollowup) {
        await api.post('/followups', {
          company_id: companyId, title: fTitle.trim(), due_at: new Date(fDue).toISOString(),
        });
      }
      toast('success', 'First touch logged to communications.');
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not log the touchpoint.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Log first touch — {companyName}?</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">Record how contact was made. It lands in Communications under this company.</p>
        <div className="grid grid-cols-2 gap-2 text-sm">
          <label>Type<select value={type} onChange={(e) => setType(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            {TOUCH_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
          </select></label>
          <label>Outcome<select value={outcome} onChange={(e) => setOutcome(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            {OUTCOMES.map((t) => <option key={t} value={t}>{t}</option>)}
          </select></label>
        </div>
        <label className="mt-2 block text-sm">Notes *<textarea required value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} placeholder="What happened, what was agreed…" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
        <label className="mt-3 flex cursor-pointer items-center gap-2 text-sm font-medium">
          <input type="checkbox" checked={addFollowup} onChange={(e) => setAddFollowup(e.target.checked)} />
          Set a follow-up
        </label>
        {addFollowup && (
          <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
            <label>Title *<input value={fTitle} onChange={(e) => setFTitle(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Due *<input type="datetime-local" value={fDue} onChange={(e) => setFDue(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
        )}
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-between gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Skip for now</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Logging…' : 'Log touch'}</button>
        </div>
      </form>
    </div>
  );
}
