import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import type { DashboardData } from '@/lib/types'
import { ProjectTable } from '@/components/project-table'
import { ModuleList } from '@/components/module-list'
import { buttonVariants } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
  const mine = modules.filter((module) => module.isOpen === 0 || module.isAudit === 0)
  const publicModules = modules.filter((module) => module.isOpen === 1 && module.isAudit === 1)

  return (
    <div className="flex flex-col gap-6">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
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
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>我的模块</CardTitle>
            <Badge variant="secondary">{mine.length}</Badge>
          </CardHeader>
          <CardContent>
            <ModuleList
              modules={mine}
              href={(module) => `/modules/${module.id}/edit`}
              emptyTitle="暂无私有模块"
              emptyDescription="创建模块后可在这里快速编辑"
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>公共模块</CardTitle>
            <Badge variant="secondary">{publicModules.length}</Badge>
          </CardHeader>
          <CardContent>
            <ModuleList
              modules={publicModules}
              href={(module) => `/modules/${module.id}`}
              emptyTitle="暂无公共模块"
              emptyDescription="审核通过的公共模块会显示在这里"
            />
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
