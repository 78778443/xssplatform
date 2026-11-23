import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { ModuleGetData } from '@/lib/types'
import { ModuleForm } from '@/components/module-form'
import { buttonVariants } from '@/components/ui/button'

export function ModuleViewPage() {
  const { id } = useParams()
  const moduleId = Number(id)
  const query = useQuery({
    queryKey: ['module', moduleId],
    queryFn: () => api<ModuleGetData>('module.get', { query: { id: String(moduleId) } }),
    enabled: Number.isFinite(moduleId),
  })

  if (query.isLoading) return <p className="text-sm text-muted-foreground">加载中…</p>
  if (!query.data) return <p className="text-sm text-muted-foreground">模块不存在或没有权限</p>
  const data = query.data

  return (
    <div className="flex flex-col gap-4">
      <ModuleForm
        heading="查看模块信息"
        initial={{
          title: data.module.title,
          description: data.module.description,
          keys: data.keys,
          setkeys: data.setkeys,
          code: data.module.code,
          isOpen: data.module.isOpen === 1,
        }}
        submitLabel="保存"
        readOnly
        onSubmit={() => {}}
      />
      <div className="flex gap-2">
        {data.canEdit && (
          <Link to={`/modules/${moduleId}/edit`} className={buttonVariants()}>
            编辑
          </Link>
        )}
        <Link to="/modules" className={buttonVariants({ variant: 'outline' })}>
          返回
        </Link>
      </div>
    </div>
  )
}
