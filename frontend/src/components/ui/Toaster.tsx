import { createContext, useCallback, useContext, useState, type ReactNode } from 'react';

type Toast = { id: number; kind: 'success' | 'error' | 'info'; text: string };
const Ctx = createContext<(kind: Toast['kind'], text: string) => void>(() => {});

export const useToast = () => useContext(Ctx);

export function Toaster({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const push = useCallback((kind: Toast['kind'], text: string) => {
    const id = Date.now() + Math.random();
    setToasts((t) => [...t, { id, kind, text }]);
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 4500);
  }, []);
  const color = (k: Toast['kind']) =>
    k === 'success' ? 'border-green-500' : k === 'error' ? 'border-red-500' : 'border-sky-500';
  return (
    <Ctx.Provider value={push}>
      {children}
      <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2" aria-live="polite">
        {toasts.map((t) => (
          <div key={t.id} className={`card border-l-4 px-4 py-3 shadow-sm ${color(t.kind)}`}>
            {t.text}
          </div>
        ))}
      </div>
    </Ctx.Provider>
  );
}
