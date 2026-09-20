import { Component, type ReactNode } from 'react';

/**
 * Catches render crashes (bad row shapes, unexpected nulls) so one bad page
 * can never blank the whole app again. Offers reset + navigation recovery.
 */
export class ErrorBoundary extends Component<{ children: ReactNode }, { error: Error | null }> {
  state = { error: null as Error | null };

  static getDerivedStateFromError(error: Error) {
    return { error };
  }

  componentDidCatch(error: Error) {
    // Visible in devtools; never sent anywhere (no tracker in v1).
    console.error('[crm] render crash contained:', error);
  }

  render() {
    if (!this.state.error) return this.props.children;
    return (
      <div className="mx-auto mt-16 w-full max-w-md p-4">
        <div className="card border-l-4 border-l-red-500 p-6">
          <h1 className="text-lg font-bold">Something went wrong on this page</h1>
          <p className="mt-1 text-sm text-[var(--text-muted)]">
            The error was contained — the rest of the app still works. Details:{' '}
            <code className="text-xs">{this.state.error.message}</code>
          </p>
          <div className="mt-4 flex gap-2">
            <button
              onClick={() => this.setState({ error: null })}
              className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white"
            >
              Try again
            </button>
            <button
              onClick={() => {
                this.setState({ error: null });
                window.location.hash = '#/';
                window.location.pathname = '/';
              }}
              className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm"
            >
              Go to dashboard
            </button>
          </div>
        </div>
      </div>
    );
  }
}
