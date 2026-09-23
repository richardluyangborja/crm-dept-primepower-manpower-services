import { Monitor, Moon, Sun } from 'lucide-react';
import { useSession } from '../../store/session';

const ORDER = ['light', 'dark', 'system'] as const;

/** Three-state theme toggle: light → dark → system → light. Never loses state. */
export function ThemeToggle() {
  const { theme, setTheme } = useSession();
  const next = ORDER[(ORDER.indexOf(theme) + 1) % ORDER.length];
  return (
    <button
      aria-label={`Theme: ${theme}. Switch to ${next}.`}
      className="rounded-lg border border-[var(--border)] p-2"
      onClick={() => setTheme(next)}
      title={`Theme: ${theme} — switch to ${next}`}
    >
      {theme === 'dark' ? <Sun size={18} /> : theme === 'system' ? <Monitor size={18} /> : <Moon size={18} />}
    </button>
  );
}
