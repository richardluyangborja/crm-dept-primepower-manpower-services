import { useEffect } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AppShell } from './components/layout/AppShell';
import { Toaster } from './components/ui/Toaster';
import { ErrorBoundary } from './components/ui/ErrorBoundary';
import { applyTheme, hasRole, useSession } from './store/session';
import { LoginPage } from './pages/Login';
import { DashboardPage } from './pages/Dashboard';
import { ReportsPage } from './pages/Reports';
import { SettingsPage } from './pages/Settings';
import { SurveysPage } from './pages/Surveys';
import { RespondPage } from './pages/Respond';
import { CommsPage } from './pages/Comms';
import { FollowupsPage } from './pages/Followups';
import { LeadsPage } from './pages/Leads';
import { LeadPage } from './pages/LeadPage';
import { ClientPage } from './pages/ClientPage';
import { ClientsPage } from './pages/Clients';
import { PipelinePage } from './pages/Pipeline';
import { PipelineFinancePage } from './pages/PipelineFinance';
import { PipelineStaffingPage } from './pages/PipelineStaffing';
import { PipelineContractsPage } from './pages/PipelineContracts';

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
      <BrowserRouter>
        <Toaster>
          <ErrorBoundary>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/s/:token" element={<RespondPage />} />
            <Route
              element={
                <RequireAuth>
                  <AppShell />
                </RequireAuth>
              }
            >
              <Route index element={<DashboardPage />} />
              <Route path="leads" element={<LeadsPage />} />
              <Route path="leads/:id" element={<LeadPage />} />
              <Route path="clients" element={<ClientsPage />} />
              <Route path="clients/:id" element={<ClientPage />} />
              <Route path="pipeline" element={<PipelinePage />} />
              <Route path="pipeline/finance" element={<PipelineFinancePage />} />
              <Route path="pipeline/staffing" element={<PipelineStaffingPage />} />
              <Route path="pipeline/contracts" element={<PipelineContractsPage />} />
              <Route path="comms" element={<CommsPage />} />
              <Route path="surveys" element={<SurveysPage />} />
              <Route path="followups" element={<FollowupsPage />} />
              <Route path="reports" element={<ReportsPage />} />
              <Route path="finance" element={<Navigate to="/pipeline/finance" replace />} />
              <Route path="operations" element={<Navigate to="/pipeline/staffing" replace />} />
              <Route path="settings" element={<SettingsPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
          </ErrorBoundary>
        </Toaster>
      </BrowserRouter>
    </QueryClientProvider>
  );
}
