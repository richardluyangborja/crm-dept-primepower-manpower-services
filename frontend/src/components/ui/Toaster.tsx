import { createContext, useCallback, useContext, useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { AlertCircle, CheckCircle2, Info } from 'lucide-react';

type ToastKind = 'success' | 'error' | 'info';
export type ToastInput = string | { title: string; body?: string; action?: { label: string; href: string } };
type Toast = { id: number; kind: ToastKind; title: string; body?: string; action?: { label: string; href: string } };
const Ctx = createContext<(kind: ToastKind, text: ToastInput) => void>(() => {});

export const useToast = () => useContext(Ctx);

const ICONS = {
  success: <CheckCircle2 size={18} className="mt-0.5 shrink-0 text-green-600" />,
  error: <AlertCircle size={18} className="mt-0.5 shrink-0 text-red-600" />,
  info: <Info size={18} className="mt-0.5 shrink-0 text-sky-600" />,
};

function split(input: ToastInput): Omit<Toast, 'id' | 'kind'> {
  if (typeof input !== 'string') return input;
  // "Title — body" convention upgrades all existing call sites for free.
  const i = input.indexOf(' — ');
  if (i > 0) return { title: input.slice(0, i), body: input.slice(i + 3) };
  return { title: input };
}

export function Toaster({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const push = useCallback((kind: ToastKind, text: ToastInput) => {
    const id = Date.now() + Math.random();
    setToasts((t) => [...t, { id, kind, ...split(text) }]);
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 5000);
  }, []);
  const color = (k: ToastKind) =>
    k === 'success' ? 'border-green-500' : k === 'error' ? 'border-red-500' : 'border-sky-500';
  return (
    <Ctx.Provider value={push}>
      {children}
      <div className="fixed bottom-4 right-4 z-50 flex max-w-sm flex-col gap-2" aria-live="polite">
        {toasts.map((t) => (
          <div key={t.id} className={`card flex gap-2.5 border-l-4 px-4 py-3 shadow-md ${color(t.kind)}`}>
            {ICONS[t.kind]}
            <div className="min-w-0">
              <p className="text-sm font-semibold">{t.title}</p>
              {t.body && <p className="mt-0.5 text-xs text-[var(--text-muted)]">{t.body}</p>}
              {t.action && (
                <Link to={t.action.href} className="mt-1 inline-block text-xs font-medium text-sky-700 hover:underline dark:text-sky-300">
                  {t.action.label} →
                </Link>
              )}
            </div>
          </div>
        ))}
      </div>
    </Ctx.Provider>
  );
}
