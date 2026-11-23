import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronDown, Code2, Copy, Download, Link2, SlidersHorizontal } from 'lucide-react'
import { toast } from 'sonner'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import type { ProjectViewData } from '@/lib/types'
import { ContentTable } from '@/components/content-table'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { PageHeader } from '@/components/layout/page-header'
import { Label } from '@/components/ui/label'
import { QueryError } from '@/components/query-error'

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
  const contents = query.data?.contents ?? []

  async function copyEndpoint() {
    if (!project) return
    try {
      await navigator.clipboard.writeText(`/do/auth/${project.authCode}`)
      toast.success('收包地址已复制')
    } catch {
      toast.error('复制失败，请手动选择')
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        eyebrow="Project"
        title={project?.title ?? '项目内容'}
        description="实时收包结果，按域名过滤并按需批量清理。"
        actions={
          <>
            <Link to={`/project/${projectId}/code`} className={buttonVariants({ variant: 'outline' })}>
              <Code2 />
              查看代码
            </Link>
            <Link to={`/project/${projectId}/config`} className={buttonVariants()}>
              <SlidersHorizontal />
              配置
            </Link>
          </>
        }
      />
      {query.isError && <QueryError error={query.error} />}
      <Card>
      <CardHeader className="grid-cols-1 gap-3">
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
          <div className="flex w-full flex-col gap-1.5 sm:w-auto">
            <Label htmlFor="domain-filter" className="text-xs text-muted-foreground">
              域名筛选
            </Label>
            <div className="relative">
              <select
                  id="domain-filter"
                  className="h-9 w-full appearance-none rounded-lg border border-input/80 bg-card pr-8 pl-3 text-sm shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition-colors outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/20 sm:w-56"
                  value={domain}
                  onChange={(event) => {
                    const next = new URLSearchParams(params)
                    if (event.target.value) next.set('domain', event.target.value)
                    else next.delete('domain')
                    setParams(next)
                  }}
                >
                  <option value="">全部域名</option>
                  {domains.map((item) => (
                    <option key={item} value={item}>
                      {item}
                    </option>
                  ))}
                </select>
                <ChevronDown className="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
              </div>
            </div>
          <a
            href="/xsser.crx"
            download
            className={buttonVariants({ variant: 'outline', size: 'sm', className: 'self-start sm:mb-0.5' })}
          >
            <Download />
            安装插件
          </a>
        </div>
        {project && (
          <div className="flex items-center gap-2.5 rounded-lg border border-border/60 bg-muted/25 px-3 py-2">
            <Link2 className="size-3.5 shrink-0 text-muted-foreground" />
            <span className="shrink-0 text-xs text-muted-foreground">收包地址</span>
            <code className="min-w-0 flex-1 truncate font-mono text-xs text-foreground">
              /do/auth/{project.authCode}
            </code>
            <Button
              variant="ghost"
              size="icon-sm"
              aria-label="复制收包地址"
              title="复制收包地址"
              className="text-muted-foreground"
              onClick={copyEndpoint}
            >
              <Copy className="size-3.5" />
            </Button>
          </div>
        )}
      </CardHeader>
      <CardContent className="p-0">
        {query.isLoading ? (
          <p className="px-5 pb-1 text-sm text-muted-foreground">加载中…</p>
        ) : (
          <ContentTable
            contents={contents}
            token={session?.csrf ?? ''}
            onDelete={(contentId) => removeContent.mutate(contentId)}
            onDeleteMany={(ids) => removeContents.mutate(ids)}
          />
        )}
      </CardContent>
      </Card>
    </div>
  )
}
