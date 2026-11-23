import { Route, Routes } from 'react-router-dom'
import { AppShell } from '@/components/layout/app-shell'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

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
        <Route path="/" element={<Placeholder title="我的项目" />} />
        <Route path="/login" element={<Placeholder title="登陆" />} />
        <Route path="/register" element={<Placeholder title="注册" />} />
        <Route path="/install" element={<Placeholder title="安装向导" />} />
        <Route path="/project/new" element={<Placeholder title="创建项目" />} />
        <Route path="/project/:id" element={<Placeholder title="项目内容" />} />
        <Route path="/project/:id/config" element={<Placeholder title="配置代码" />} />
        <Route path="/project/:id/code" element={<Placeholder title="项目代码" />} />
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
