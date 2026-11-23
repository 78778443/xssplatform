import { Route, Routes } from 'react-router-dom'
import { AppShell } from '@/components/layout/app-shell'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { LoginPage } from '@/pages/login-page'
import { RegisterPage } from '@/pages/register-page'
import { InstallPage } from '@/pages/install-page'
import { DashboardPage } from '@/pages/dashboard-page'
import { ProjectNewPage } from '@/pages/project-new-page'
import { ProjectViewPage } from '@/pages/project-view-page'
import { ProjectConfigPage } from '@/pages/project-config-page'
import { ProjectCodePage } from '@/pages/project-code-page'

function Placeholder({ title }: { title: string }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent className="text-sm text-muted-foreground">页面建设中</CardContent>
    </Card>
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
        <Route path="/modules" element={<Placeholder title="模块" />} />
        <Route path="/modules/new" element={<Placeholder title="创建模块" />} />
        <Route path="/modules/:id" element={<Placeholder title="查看模块" />} />
        <Route path="/modules/:id/edit" element={<Placeholder title="配置模块" />} />
        <Route path="/settings" element={<Placeholder title="个人设置" />} />
        <Route path="/invites" element={<Placeholder title="邀请码" />} />
        <Route path="/admin" element={<Placeholder title="管理后台" />} />
        <Route path="/admin/modules" element={<Placeholder title="模块审核" />} />
        <Route path="*" element={<Placeholder title="页面不存在" />} />
      </Routes>
    </AppShell>
  )
}
