import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { ModuleGetData } from '@/lib/types'
import { buttonVariants } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PageHeader } from '@/components/layout/page-header'
import { CodeBlock } from '@/components/code-block'
import { QueryError } from '@/components/query-error'

export function ModuleViewPage() {
  const { id } = useParams()
  const moduleId = Number(id)
  const query = useQuery({
    queryKey: ['module', moduleId],
    queryFn: () => api<ModuleGetData>('module.get', { query: { id: String(moduleId) } }),
    enabled: Number.isFinite(moduleId),
  })

  if (query.isLoading) return <p className="text-sm text-muted-foreground">加载中…</p>
  if (query.isError) return <QueryError error={query.error} />
  if (!query.data) return <p className="text-sm text-muted-foreground">模块不存在或没有权限</p>

  const data = query.data
  const isPublic = data.module.isOpen === 1 && data.module.isAudit === 1
  const pending = data.module.isOpen === 1 && data.module.isAudit !== 1

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        eyebrow="Module"
        title={data.module.title}
        description={data.module.description || '暂无描述'}
        actions={
          <>
            {data.canEdit && (
              <Link to={`/modules/${moduleId}/edit`} className={buttonVariants()}>
                编辑
              </Link>
            )}
            <Link to="/modules" className={buttonVariants({ variant: 'outline' })}>
              返回
            </Link>
          </>
        }
      />

      <Card className="max-w-4xl">
        <CardHeader className="flex flex-row items-center gap-2">
          <CardTitle>模块信息</CardTitle>
          <Badge variant={isPublic ? 'secondary' : pending ? 'destructive' : 'outline'}>
            {isPublic ? '公共模块' : pending ? '待审核' : '私有模块'}
          </Badge>
        </CardHeader>
        <CardContent className="flex flex-col gap-5">
          <div className="flex flex-col gap-2">
            <span className="text-xs font-medium text-muted-foreground">收集参数</span>
            <div className="flex flex-wrap gap-1.5">
              {data.keys.length > 0 ? (
                data.keys.map((key) => (
                  <Badge key={key} variant="outline" className="font-mono">
                    {key}
                  </Badge>
                ))
              ) : (
                <span className="text-sm text-muted-foreground">无</span>
              )}
            </div>
          </div>

          <div className="flex flex-col gap-2">
            <span className="text-xs font-medium text-muted-foreground">配置参数</span>
            <div className="flex flex-wrap gap-1.5">
              {data.setkeys.length > 0 ? (
                data.setkeys.map((key) => (
                  <Badge key={key} variant="outline" className="font-mono">
                    {key}
                  </Badge>
                ))
              ) : (
                <span className="text-sm text-muted-foreground">无</span>
              )}
            </div>
          </div>

          <CodeBlock label="模块代码" value={data.module.code} />
        </CardContent>
      </Card>
    </div>
  )
}
