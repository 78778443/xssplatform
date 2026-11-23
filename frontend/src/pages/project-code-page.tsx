import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { ProjectCode } from '@/lib/types'
import { CodeBlock } from '@/components/code-block'
import { buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function ProjectCodePage() {
  const { id } = useParams()
  const projectId = Number(id)
  const [params] = useSearchParams()
  const created = params.get('ty') === 'create'
  const query = useQuery({
    queryKey: ['project-code', projectId],
    queryFn: () => api<ProjectCode>('project.viewcode', { query: { id: String(projectId) } }),
    enabled: Number.isFinite(projectId),
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle>项目代码</CardTitle>
        <CardDescription>
          将下面任一代码植入怀疑出现 XSS 的位置（注意 &apos; 的转义），即可在项目内容中查看效果。
        </CardDescription>
      </CardHeader>
      <CardContent>
        {query.isLoading ? (
          <p className="text-sm text-muted-foreground">加载中…</p>
        ) : (
          query.data && (
            <div className="flex flex-col gap-6">
              <div className="grid items-start gap-6 lg:grid-cols-2">
                <CodeBlock label="项目代码" value={query.data.code} />
                <CodeBlock label="方式一" value={query.data.scriptShow1} />
                <CodeBlock className="lg:col-span-2" label="方式二" value={query.data.scriptShow2} />
                <CodeBlock label="代码地址" value={query.data.codeurl} />
                <CodeBlock label="短网址/长链接" value={query.data.shortShow1} />
                <CodeBlock className="lg:col-span-2" label="图片触发" value={query.data.shortShow3} />
              </div>
              <div>
                <Link to="/" className={buttonVariants()}>
                  {created ? '完成' : '返回首页'}
                </Link>
              </div>
            </div>
          )
        )}
      </CardContent>
    </Card>
  )
}
