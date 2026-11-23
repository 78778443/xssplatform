import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import type { ProjectConfig } from '@/lib/types'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

export function ProjectConfigPage({ id }: { id?: number }) {
  const params = useParams()
  const projectId = id ?? Number(params.id)
  const [search] = useSearchParams()
  const created = search.get('created') === '1'
  const { data: session } = useSession()
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const query = useQuery({
    queryKey: ['project-config', projectId],
    queryFn: () => api<ProjectConfig>('project.setcode.get', { query: { id: String(projectId) } }),
    enabled: Number.isFinite(projectId),
  })

  const [selected, setSelected] = useState<Record<number, boolean>>({})
  const [setkeyValues, setSetkeyValues] = useState<Record<string, string>>({})
  const [code, setCode] = useState('')
  const [error, setError] = useState('')
  const [pending, setPending] = useState(false)

  useEffect(() => {
    if (!query.data) return
    const nextSelected: Record<number, boolean> = {}
    const nextSetkeys: Record<string, string> = {}
    for (const module of query.data.modulesCan) {
      if (module.choosed) nextSelected[module.id] = true
      for (const item of module.setkeys ?? []) {
        nextSetkeys[`setkey_${module.id}_${item.key}`] = item.value ?? ''
      }
    }
    setSelected(nextSelected)
    setSetkeyValues(nextSetkeys)
    setCode(query.data.project.code ?? '')
  }, [query.data])

  async function save() {
    if (!query.data) return
    setError('')
    setPending(true)
    try {
      const modules = Object.keys(selected)
        .filter((key) => selected[Number(key)])
        .map(Number)
      const body: Record<string, unknown> = {
        id: projectId,
        ty: query.data.ty || (created ? 'create' : 'edit'),
        modules,
        code,
      }
      for (const [key, value] of Object.entries(setkeyValues)) {
        if (value !== '') body[key] = value
      }
      await api('project.setcode.save', { method: 'POST', csrf: session?.csrf, body })
      await queryClient.invalidateQueries({ queryKey: ['project', projectId] })
      if (created) {
        navigate(`/project/${projectId}/code`)
      } else {
        navigate(`/project/${projectId}`)
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : '保存失败')
    } finally {
      setPending(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>配置代码：{query.data?.project.title ?? ''}</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">
        <div className="flex flex-col gap-3">
          {(query.data?.modulesCan ?? []).map((module) => {
            const isModuleOne = module.id === 1
            return (
              <div key={module.id} className="rounded-lg border p-3">
                <label className="flex items-center gap-2 text-sm font-medium">
                  <Checkbox
                    checked={Boolean(selected[module.id])}
                    onCheckedChange={(checked) =>
                      setSelected((prev) => ({ ...prev, [module.id]: checked === true }))
                    }
                  />
                  {module.title}
                </label>
                {(module.setkeys ?? []).length > 0 && Boolean(selected[module.id]) && (
                  <div className="mt-2 flex flex-col gap-2 text-sm">
                    <span className="text-muted-foreground">需要配置的参数</span>
                    {(module.setkeys ?? []).map((item) => {
                      const field = `setkey_${module.id}_${item.key}`
                      if (isModuleOne && item.key === 'keepsession') {
                        return (
                          <div key={field} className="flex items-center gap-4">
                            <label className="flex items-center gap-2">
                              <input
                                type="radio"
                                name={field}
                                checked={setkeyValues[field] !== '1'}
                                onChange={() => setSetkeyValues((prev) => ({ ...prev, [field]: '0' }))}
                              />
                              无 keepsession
                            </label>
                            <label className="flex items-center gap-2">
                              <input
                                type="radio"
                                name={field}
                                checked={setkeyValues[field] === '1'}
                                onChange={() => setSetkeyValues((prev) => ({ ...prev, [field]: '1' }))}
                              />
                              keepsession
                            </label>
                          </div>
                        )
                      }
                      return (
                        <div key={field} className="flex flex-col gap-1">
                          <Label htmlFor={field}>{item.key}</Label>
                          <Input
                            id={field}
                            value={setkeyValues[field] ?? ''}
                            onChange={(event) =>
                              setSetkeyValues((prev) => ({ ...prev, [field]: event.target.value }))
                            }
                          />
                        </div>
                      )
                    })}
                  </div>
                )}
                <details className="mt-2 text-sm">
                  <summary className="cursor-pointer text-muted-foreground">展开查看参数与代码</summary>
                  <pre className="mt-2 overflow-auto rounded border bg-muted/40 p-2 font-mono text-xs break-all whitespace-pre-wrap">
参数:{module.keys}
代码:{module.code}</pre>
                </details>
              </div>
            )
          })}
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="project-code">自定义代码</Label>
          <Textarea
            id="project-code"
            className="min-h-40 font-mono text-xs"
            value={code}
            onChange={(event) => setCode(event.target.value)}
          />
        </div>

        {error && <p className="text-sm text-destructive">{error}</p>}
        <div className="flex gap-2">
          <Button onClick={save} disabled={pending}>
            {created ? '下一步' : '配置'}
          </Button>
          <Button variant="outline" onClick={() => history.go(-1)}>
            取消
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
