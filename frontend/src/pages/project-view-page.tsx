import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download } from 'lucide-react'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import type { ProjectViewData } from '@/lib/types'
import { ContentTable } from '@/components/content-table'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'

export function ProjectViewPage() {
  const { id } = useParams()
  const projectId = Number(id)
  const [params, setParams] = useSearchParams()
  const domain = params.get('domain') ?? ''
  const { data: session } = useSession()
  const queryClient = useQueryClient()

  const query = useQuery({
    queryKey: ['project', projectId, domain],
    queryFn: () =>
      api<ProjectViewData>('project.get', {
        query: { id: String(projectId), ...(domain ? { domain } : {}) },
      }),
    enabled: Number.isFinite(projectId),
  })

  const removeContent = useMutation({
    mutationFn: (contentId: number) =>
      api('project.contents.delete', { method: 'POST', csrf: session?.csrf, body: { id: contentId } }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['project', projectId] }),
  })

  const removeContents = useMutation({
    mutationFn: (ids: number[]) =>
      api('project.contents.deleteMany', { method: 'POST', csrf: session?.csrf, body: { ids } }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['project', projectId] }),
  })

  const project = query.data?.project
  const domains = query.data?.domains ?? []

  return (
    <Card>
      <CardHeader className="gap-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <CardTitle>{project?.title ?? '项目内容'}</CardTitle>
          <div className="flex gap-2">
            <Link to={`/project/${projectId}/code`} className={buttonVariants({ variant: 'outline', size: 'sm' })}>
              查看代码
            </Link>
            <Link to={`/project/${projectId}/config`} className={buttonVariants({ variant: 'outline', size: 'sm' })}>
              配置
            </Link>
          </div>
        </div>
        <div className="flex flex-wrap items-end gap-3 text-sm">
          <div className="flex flex-col gap-1">
            <Label htmlFor="domain-filter">Domain</Label>
            <select
              id="domain-filter"
              className="h-8 rounded-lg border border-input bg-transparent px-2 text-sm"
              value={domain}
              onChange={(event) => {
                const next = new URLSearchParams(params)
                if (event.target.value) next.set('domain', event.target.value)
                else next.delete('domain')
                setParams(next)
              }}
            >
              <option value="">全部</option>
              {domains.map((item) => (
                <option key={item} value={item}>
                  {item}
                </option>
              ))}
            </select>
          </div>
          {project && (
            <span className="text-muted-foreground">
              接口地址: /do/auth/{project.authCode}（加 /domain/xxx 可通过域名过滤内容）
            </span>
          )}
          <a href="/xsser.crx" download className={buttonVariants({ variant: 'outline', size: 'sm' })}>
            <Download />
            安装插件
          </a>
        </div>
      </CardHeader>
      <CardContent>
        {query.isLoading ? (
          <p className="text-sm text-muted-foreground">加载中…</p>
        ) : (
          <ContentTable
            contents={query.data?.contents ?? []}
            token={session?.csrf ?? ''}
            onDelete={(contentId) => removeContent.mutate(contentId)}
            onDeleteMany={(ids) => removeContents.mutate(ids)}
          />
        )}
      </CardContent>
    </Card>
  )
}
