import { useEffect } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AppShell } from './components/layout/AppShell';
import { Toaster } from './components/ui/Toaster';
import { applyTheme, hasRole, useSession } from './store/session';
import { LoginPage } from './pages/Login';
import { DashboardPage } from './pages/Dashboard';
import { CommsPage, ReportsPage, SettingsPage, SurveysPage } from './pages/shells';
import { FollowupsPage } from './pages/Followups';
import { LeadsPage } from './pages/Leads';
import { PipelinePage } from './pages/Pipeline';

const qc = new QueryClient({ defaultOptions: { queries: { retry: 2, refetchOnWindowFocus: false } } });

function RequireAuth({ children }: { children: React.ReactNode }) {
  const { user } = useSession();
  if (!user && !sessionStorage.getItem('crm.access')) return <Navigate to="/login" replace />;
  return <>{children}</>;
}

export default function App() {
  const { theme } = useSession();
  useEffect(() => applyTheme(theme), [theme]);
  void hasRole;

  return (
    <QueryClientProvider client={qc}>
      <Toaster>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route
              element={
                <RequireAuth>
                  <AppShell />
                </RequireAuth>
              }
            >
              <Route index element={<DashboardPage />} />
              <Route path="leads" element={<LeadsPage />} />
              <Route path="pipeline" element={<PipelinePage />} />
              <Route path="comms" element={<CommsPage />} />
              <Route path="surveys" element={<SurveysPage />} />
              <Route path="followups" element={<FollowupsPage />} />
              <Route path="reports" element={<ReportsPage />} />
              <Route path="settings" element={<SettingsPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </BrowserRouter>
      </Toaster>
    </QueryClientProvider>
  );
}
