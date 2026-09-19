import { Moon, Sun } from 'lucide-react';
import { useSession } from '../../store/session';

export function ThemeToggle() {
  const { theme, setTheme } = useSession();
  const next = theme === 'dark' ? 'light' : 'dark';
  return (
    <button
      aria-label="Toggle theme"
      className="rounded-lg border border-[var(--border)] p-2"
      onClick={() => setTheme(next)}
      title={`Switch to ${next} (current: ${theme})`}
    >
      {theme === 'dark' ? <Sun size={18} /> : <Moon size={18} />}
    </button>
  );
}
