import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import type { ModuleGetData } from '@/lib/types'
import { ModuleForm, type ModuleFormValue } from '@/components/module-form'

export function ModuleEditPage() {
  const { id } = useParams()
  const moduleId = Number(id)
  const { data: session } = useSession()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [error, setError] = useState('')
  const [pending, setPending] = useState(false)
  const [initial, setInitial] = useState<ModuleFormValue | null>(null)

  const query = useQuery({
    queryKey: ['module', moduleId],
    queryFn: () => api<ModuleGetData>('module.get', { query: { id: String(moduleId) } }),
    enabled: Number.isFinite(moduleId),
  })

  useEffect(() => {
    if (!query.data) return
    setInitial({
      title: query.data.module.title,
      description: query.data.module.description,
      keys: query.data.keys,
      setkeys: query.data.setkeys,
      code: query.data.module.code,
      isOpen: query.data.module.isOpen === 1,
    })
  }, [query.data])

  async function submit(value: ModuleFormValue) {
    setError('')
    if (value.title.trim() === '') {
      setError('模块名称不能为空')
      return
    }
    setPending(true)
    try {
      await api('module.save', {
        method: 'POST',
        csrf: session?.csrf,
        body: {
          id: moduleId,
          title: value.title,
          description: value.description,
          keys: value.keys,
          setkeys: value.setkeys,
          code: value.code,
          isOpen: value.isOpen ? 1 : 0,
        },
      })
      await queryClient.invalidateQueries({ queryKey: ['module', moduleId] })
      navigate(`/modules/${moduleId}`)
    } catch (err) {
      setError(err instanceof Error ? err.message : '保存失败')
    } finally {
      setPending(false)
    }
  }

  if (query.isLoading) return <p className="text-sm text-muted-foreground">加载中…</p>
  if (!query.data) return <p className="text-sm text-muted-foreground">模块不存在或没有权限</p>

  if (!query.data.canEdit) {
    return <p className="text-sm text-destructive">已通过审核的公共模块不能修改</p>
  }
  if (!initial) return null

  return (
    <ModuleForm
      heading="配置模块"
      initial={initial}
      submitLabel="配置"
      pending={pending}
      error={error}
      onSubmit={submit}
    />
  )
}
