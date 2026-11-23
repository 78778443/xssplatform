import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import type { DashboardData } from '@/lib/types'
import { ProjectTable } from '@/components/project-table'
import { buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export function DashboardPage() {
  const { data: session } = useSession()
  const queryClient = useQueryClient()
  const dashboard = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => api<DashboardData>('dashboard'),
  })

  const remove = useMutation({
    mutationFn: (id: number) => api('project.delete', { method: 'POST', csrf: session?.csrf, body: { id } }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['dashboard'] }),
  })

  const modules = dashboard.data?.modules ?? []

  return (
    <div className="flex flex-col gap-6">
      <Card>
        <CardHeader className="flex-row items-center justify-between">
          <CardTitle>我的项目</CardTitle>
          <Link to="/project/new" className={buttonVariants({ size: 'sm' })}>
            <Plus />
            创建项目
          </Link>
        </CardHeader>
        <CardContent>
          {dashboard.isLoading ? (
            <p className="text-sm text-muted-foreground">加载中…</p>
          ) : (
            <ProjectTable
              projects={dashboard.data?.projects ?? []}
              onDelete={(id) => remove.mutate(id)}
            />
          )}
        </CardContent>
      </Card>

      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>我的模块</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="flex flex-col gap-2 text-sm">
              {modules
                .filter((module) => module.isOpen === 0 || module.isAudit === 0)
                .map((module) => (
                  <li key={module.id}>
                    <Link to={`/modules/${module.id}/edit`}>{module.title}</Link>
                  </li>
                ))}
              {modules.filter((module) => module.isOpen === 0 || module.isAudit === 0).length === 0 && (
                <li className="text-muted-foreground">暂无私有模块</li>
              )}
            </ul>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>公共模块</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="flex flex-col gap-2 text-sm">
              {modules
                .filter((module) => module.isOpen === 1 && module.isAudit === 1)
                .map((module) => (
                  <li key={module.id}>
                    <Link to={`/modules/${module.id}`}>{module.title}</Link>
                  </li>
                ))}
              {modules.filter((module) => module.isOpen === 1 && module.isAudit === 1).length === 0 && (
                <li className="text-muted-foreground">暂无公共模块</li>
              )}
            </ul>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

