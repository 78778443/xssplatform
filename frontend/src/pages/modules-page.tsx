import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { api } from '@/lib/api'
import type { ModuleSummary } from '@/lib/types'
import { buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface ModulesData {
  modules: ModuleSummary[]
}

export function ModulesPage() {
  const query = useQuery({
    queryKey: ['modules'],
    queryFn: () => api<ModulesData>('modules.list'),
  })
  const modules = query.data?.modules ?? []
  const mine = modules.filter((module) => module.isOpen === 0 || module.isAudit === 0)
  const publicModules = modules.filter((module) => module.isOpen === 1 && module.isAudit === 1)

  return (
    <div className="flex flex-col gap-6">
      <Card>
        <CardHeader className="flex-row items-center justify-between">
          <CardTitle>我的模块</CardTitle>
          <Link to="/modules/new" className={buttonVariants({ size: 'sm' })}>
            <Plus />
            创建模块
          </Link>
        </CardHeader>
        <CardContent>
          <ul className="flex flex-col gap-2 text-sm">
            {mine.map((module) => (
              <li key={module.id}>
                <Link to={`/modules/${module.id}/edit`}>{module.title}</Link>
                {module.isOpen === 1 && module.isAudit === 0 && (
                  <span className="ml-2 text-muted-foreground">（待审核）</span>
                )}
              </li>
            ))}
            {mine.length === 0 && <li className="text-muted-foreground">暂无私有模块</li>}
          </ul>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>公共模块</CardTitle>
        </CardHeader>
        <CardContent>
          <ul className="flex flex-col gap-2 text-sm">
            {publicModules.map((module) => (
              <li key={module.id}>
                <Link to={`/modules/${module.id}`}>{module.title}</Link>
                {module.description && (
                  <span className="ml-2 text-muted-foreground">{module.description}</span>
                )}
              </li>
            ))}
            {publicModules.length === 0 && <li className="text-muted-foreground">暂无公共模块</li>}
          </ul>
        </CardContent>
      </Card>
    </div>
  )
}
