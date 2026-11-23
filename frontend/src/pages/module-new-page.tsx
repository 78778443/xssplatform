import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import { ModuleForm, DEFAULT_KEYS, type ModuleFormValue } from '@/components/module-form'

export function ModuleNewPage() {
  const { data: session } = useSession()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [error, setError] = useState('')
  const [pending, setPending] = useState(false)

  async function submit(value: ModuleFormValue) {
    setError('')
    if (value.title.trim() === '') {
      setError('模块名称不能为空')
      return
    }
    setPending(true)
    try {
      const result = await api<{ id: number }>('module.create', {
        method: 'POST',
        csrf: session?.csrf,
        body: {
          title: value.title,
          description: value.description,
          keys: value.keys,
          setkeys: value.setkeys,
          code: value.code,
          isOpen: value.isOpen ? 1 : 0,
        },
      })
      await queryClient.invalidateQueries({ queryKey: ['modules'] })
      navigate(`/modules/${result.id}`)
    } catch (err) {
      setError(err instanceof Error ? err.message : '创建失败')
    } finally {
      setPending(false)
    }
  }

  return (
    <ModuleForm
      heading="创建模块"
      initial={{ title: '', description: '', keys: [...DEFAULT_KEYS], setkeys: [], code: '', isOpen: false }}
      submitLabel="创建"
      pending={pending}
      error={error}
      onSubmit={submit}
    />
  )
}
