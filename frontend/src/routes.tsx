import { Route, Routes } from 'react-router-dom'
import { AppShell } from '@/components/layout/app-shell'
import { buttonVariants } from '@/components/ui/button'
import { EmptyState } from '@/components/empty-state'
import { Link } from 'react-router-dom'
import { LoginPage } from '@/pages/login-page'
import { RegisterPage } from '@/pages/register-page'
import { InstallPage } from '@/pages/install-page'
import { DashboardPage } from '@/pages/dashboard-page'
import { ProjectNewPage } from '@/pages/project-new-page'
import { ProjectViewPage } from '@/pages/project-view-page'
import { ProjectConfigPage } from '@/pages/project-config-page'
import { ProjectCodePage } from '@/pages/project-code-page'
import { ModulesPage } from '@/pages/modules-page'
import { ModuleNewPage } from '@/pages/module-new-page'
import { ModuleViewPage } from '@/pages/module-view-page'
import { ModuleEditPage } from '@/pages/module-edit-page'
import { SettingsPage } from '@/pages/settings-page'
import { InvitesPage } from '@/pages/invites-page'
import { AdminPage } from '@/pages/admin-page'
import { AdminModulesPage } from '@/pages/admin-modules-page'

function Placeholder({ title }: { title: string }) {
  return (
    <div className="flex flex-col items-center gap-4 py-16">
      <EmptyState title={title} description="链接可能已失效，或者页面已被移动。" />
      <Link to="/" className={buttonVariants()}>
        返回首页
      </Link>
    </div>
  )
}

export function AppRoutes() {
  return (
    <AppShell>
      <Routes>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/install" element={<InstallPage />} />
        <Route path="/project/new" element={<ProjectNewPage />} />
        <Route path="/project/:id" element={<ProjectViewPage />} />
        <Route path="/project/:id/config" element={<ProjectConfigPage />} />
        <Route path="/project/:id/code" element={<ProjectCodePage />} />
        <Route path="/modules" element={<ModulesPage />} />
        <Route path="/modules/new" element={<ModuleNewPage />} />
        <Route path="/modules/:id" element={<ModuleViewPage />} />
        <Route path="/modules/:id/edit" element={<ModuleEditPage />} />
        <Route path="/settings" element={<SettingsPage />} />
        <Route path="/invites" element={<InvitesPage />} />
        <Route path="/admin" element={<AdminPage />} />
        <Route path="/admin/modules" element={<AdminModulesPage />} />
        <Route path="*" element={<Placeholder title="页面不存在" />} />
      </Routes>
    </AppShell>
  )
}
