import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Blocks, FolderKanban, Inbox, Plus } from 'lucide-react'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import type { DashboardData } from '@/lib/types'
import { ProjectTable } from '@/components/project-table'
import { ModuleList } from '@/components/module-list'
import { buttonVariants } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PageHeader } from '@/components/layout/page-header'
import { QueryError } from '@/components/query-error'

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
  const projects = dashboard.data?.projects ?? []
  const mine = modules.filter((module) => module.isOpen === 0 || module.isAudit === 0)
  const publicModules = modules.filter((module) => module.isOpen === 1 && module.isAudit === 1)
  const contentTotal = projects.reduce((total, project) => total + (project.contentNum ?? 0), 0)

  const stats = [
    { label: '验证项目', value: projects.length, icon: FolderKanban },
    { label: '收包内容', value: contentTotal, icon: Inbox },
    { label: '可用模块', value: modules.length, icon: Blocks },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        eyebrow="Overview"
        title="工作台"
        description="集中管理授权验证项目与模块，实时查看收包结果。"
        actions={
          <Link to="/project/new" className={buttonVariants({ size: 'lg' })}>
            <Plus />
            创建项目
          </Link>
        }
      />

      {dashboard.isError && <QueryError error={dashboard.error} />}

      <Card>
        <div className="grid grid-cols-3 divide-x divide-border/60">
          {stats.map((stat) => (
            <div
              key={stat.label}
              className="flex flex-col gap-0.5 px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-5 sm:py-4"
            >
              <div className="flex flex-col gap-0.5">
                <span className="text-xs text-muted-foreground">{stat.label}</span>
                <span className="text-2xl font-semibold tracking-tight tabular-nums">
                  {stat.value}
                </span>
              </div>
              <span className="hidden size-9 items-center justify-center rounded-lg bg-primary/[0.07] text-primary ring-1 ring-primary/10 sm:flex">
                <stat.icon className="size-4" />
              </span>
            </div>
          ))}
        </div>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center gap-2">
          <CardTitle>我的项目</CardTitle>
          <Badge variant="secondary">{projects.length}</Badge>
        </CardHeader>
        <CardContent className="p-0">
          {dashboard.isLoading ? (
            <p className="px-5 pb-1 text-sm text-muted-foreground">加载中…</p>
          ) : (
            <ProjectTable projects={projects} onDelete={(id) => remove.mutate(id)} />
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
              columns={1}
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
              columns={1}
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
