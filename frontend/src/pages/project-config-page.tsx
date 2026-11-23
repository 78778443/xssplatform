import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import type { ProjectConfig } from '@/lib/types'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { PageHeader } from '@/components/layout/page-header'
import { cn } from '@/lib/utils'
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
        navigate(`/project/${projectId}/code?ty=create`)
      } else {
        navigate(`/project/${projectId}`)
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : '保存失败')
    } finally {
      setPending(false)
    }
  }

  const chosenCount = Object.values(selected).filter(Boolean).length

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        eyebrow="Project"
        title="配置项目"
        description={`${query.data?.project.title ?? ''} · 选择要注入的模块，也可以追加自定义代码。`}
      />

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div className="flex items-center gap-2">
            <CardTitle>可选模块</CardTitle>
            <Badge variant="secondary">{chosenCount} 个已选</Badge>
          </div>
        </CardHeader>
        <CardContent className="flex flex-col gap-6">
          <div className="flex flex-col gap-2">
            {(query.data?.modulesCan ?? []).map((module) => {
              const isModuleOne = module.id === 1
              const checked = Boolean(selected[module.id])
              const isPublic = module.isOpen === 1 && module.isAudit === 1
              return (
                <div
                  key={module.id}
                  className={cn(
                    'rounded-xl border transition-colors',
                    checked
                      ? 'border-primary/40 bg-primary/[0.03]'
                      : 'border-border/70 hover:bg-muted/40',
                  )}
                >
                  <label className="flex cursor-pointer items-start gap-3 px-3 py-3">
                    <Checkbox
                      className="mt-0.5"
                      checked={checked}
                      onCheckedChange={(value) =>
                        setSelected((prev) => ({ ...prev, [module.id]: value === true }))
                      }
                    />
                    <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-medium">{module.title}</span>
                        <Badge variant={isPublic ? 'secondary' : 'outline'}>
                          {isPublic ? '公共' : '私有'}
                        </Badge>
                      </div>
                      <span className="line-clamp-1 text-xs text-muted-foreground">
                        {module.description || '暂无描述'}
                      </span>
                    </div>
                  </label>

                  {checked && (module.setkeys ?? []).length > 0 && (
                    <div className="flex flex-col gap-3 border-t border-border/60 px-3 py-3 pl-10">
                      {(module.setkeys ?? []).map((item) => {
                        const field = `setkey_${module.id}_${item.key}`
                        if (isModuleOne && item.key === 'keepsession') {
                          return (
                            <div key={field} className="flex items-center justify-between gap-4">
                              <div className="flex flex-col gap-0.5">
                                <Label htmlFor={field}>keepsession</Label>
                                <span className="text-xs text-muted-foreground">
                                  定时带着 Cookie 访问目标，保持登录态
                                </span>
                              </div>
                              <Switch
                                id={field}
                                checked={setkeyValues[field] === '1'}
                                onCheckedChange={(next) =>
                                  setSetkeyValues((prev) => ({ ...prev, [field]: next ? '1' : '0' }))
                                }
                              />
                            </div>
                          )
                        }
                        return (
                          <div key={field} className="flex flex-col gap-1.5">
                            <Label htmlFor={field}>{item.key}</Label>
                            <Input
                              id={field}
                              className="h-9 max-w-md"
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

                  <details className="group border-t border-border/60 px-3 py-2 pl-10">
                    <summary className="cursor-pointer list-none text-xs text-muted-foreground hover:text-foreground">
                      <span className="group-open:hidden">展开查看参数与代码</span>
                      <span className="hidden group-open:inline">收起参数与代码</span>
                    </summary>
                    <div className="mt-2 flex flex-col gap-2 pb-2">
                      <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                        <span>参数</span>
                        {module.keys ? (
                          String(module.keys)
                            .split(',')
                            .filter(Boolean)
                            .map((key) => (
                              <Badge key={key} variant="outline" className="font-mono">
                                {key}
                              </Badge>
                            ))
                        ) : (
                          <span>无</span>
                        )}
                      </div>
                      <pre className="max-h-48 overflow-auto rounded-lg border border-border/70 bg-muted/40 p-3 font-mono text-xs break-all whitespace-pre-wrap">
                        {module.code}
                      </pre>
                    </div>
                  </details>
                </div>
              )
            })}
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="project-code">自定义代码</Label>
            <Textarea
              id="project-code"
              className="min-h-32 font-mono text-xs"
              value={code}
              onChange={(event) => setCode(event.target.value)}
              placeholder="可选的额外 JavaScript，会追加在所选模块代码之后"
            />
          </div>

          {error && <p className="text-sm text-destructive">{error}</p>}
          <div className="flex items-center gap-2 border-t border-border/70 pt-4">
            <Button onClick={save} disabled={pending}>
              {created ? '下一步' : '配置'}
            </Button>
            <Button variant="outline" onClick={() => history.go(-1)}>
              取消
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
