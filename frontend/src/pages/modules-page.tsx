import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { api } from '@/lib/api'
import type { ModuleSummary } from '@/lib/types'
import { buttonVariants } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ModuleList } from '@/components/module-list'
import { PageHeader } from '@/components/layout/page-header'

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
      <PageHeader
        eyebrow="Modules"
        title="模块库"
        description="管理私有模块，浏览已审核通过的公共 Payload 模块。"
        actions={
          <Link to="/modules/new" className={buttonVariants()}>
            <Plus />
            创建模块
          </Link>
        }
      />

      <Card>
        <CardHeader className="flex flex-row items-center gap-2">
          <CardTitle>我的模块</CardTitle>
          <Badge variant="secondary">{mine.length}</Badge>
        </CardHeader>
        <CardContent>
          <ModuleList
            modules={mine}
            href={(module) => `/modules/${module.id}/edit`}
            emptyTitle="暂无私有模块"
            emptyDescription="创建一个模块，组合要收集的字段与代码"
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div className="flex items-center gap-2">
            <CardTitle>公共模块</CardTitle>
            <Badge variant="secondary">{publicModules.length}</Badge>
          </div>
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
  )
}
