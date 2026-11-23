import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
  heading,
  initial,
  submitLabel,
  readOnly = false,
  pending = false,
  error,
  onSubmit,
}: {
  heading: string
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
    <Card>
      <CardHeader>
        <CardTitle>{heading}</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-5">
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
          <Label htmlFor="module-code">代码（{'{'}projectId{'}'} 为项目 id，{'{'}set.***{'}'} 为配置参数）</Label>
          <Textarea
            id="module-code"
            className="min-h-48 font-mono text-xs"
            value={value.code}
            readOnly={readOnly}
            onChange={(event) => update('code', event.target.value)}
          />
        </div>

        <div className="flex items-center gap-4 text-sm">
          <Label>是否公开</Label>
          <label className="flex items-center gap-2">
            <input
              type="radio"
              name="isOpen"
              checked={!value.isOpen}
              disabled={readOnly}
              onChange={() => update('isOpen', false)}
            />
            私有
          </label>
          <label className="flex items-center gap-2">
            <input
              type="radio"
              name="isOpen"
              checked={value.isOpen}
              disabled={readOnly}
              onChange={() => update('isOpen', true)}
            />
            公开
          </label>
        </div>

        {error && <p className="text-sm text-destructive">{error}</p>}
        {!readOnly && (
          <div className="flex gap-2">
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
