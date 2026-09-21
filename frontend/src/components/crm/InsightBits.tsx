import { useState } from 'react';
import api from '../../lib/apiClient';
import { ThumbsDown, ThumbsUp } from 'lucide-react';
import { useToast } from '../ui/Toaster';

export function AiBadge() {
  return (
    <span title="Rules engine v1 (mock-AI labeled) — verify before acting" className="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-900/40 dark:text-violet-200">
      AI preview ⓘ
    </span>
  );
}

export function FeedbackThumbs({ insightKey }: { insightKey: string }) {
  const toast = useToast();
  const [voted, setVoted] = useState<'up' | 'down' | null>(null);

  const vote = async (rating: 'up' | 'down') => {
    try {
      await api.post('/insights/feedback', { insight_key: insightKey, rating });
      setVoted(rating);
      toast('success', 'Thanks — feedback recorded.');
    } catch {
      toast('error', 'Could not record feedback.');
    }
  };

  return (
    <span className="inline-flex gap-1" title="Was this insight useful? Trains v2 models.">
      <button aria-label="Insight useful" onClick={() => vote('up')}
        className={`rounded px-1 text-sm ${voted === 'up' ? 'bg-green-100' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}><ThumbsUp size={14} /></button>
      <button aria-label="Insight not useful" onClick={() => vote('down')}
        className={`rounded px-1 text-sm ${voted === 'down' ? 'bg-red-100' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}><ThumbsDown size={14} /></button>
    </span>
  );
}
