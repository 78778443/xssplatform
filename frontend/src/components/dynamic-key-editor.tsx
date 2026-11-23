import { useState } from 'react'
import { X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function DynamicKeyEditor({
  label,
  value,
  onChange,
}: {
  label: string
  value: string[]
  onChange: (next: string[]) => void
}) {
  const [adding, setAdding] = useState(false)
  const [draft, setDraft] = useState('')

  function confirm() {
    const name = draft.trim()
    if (name !== '' && !value.includes(name)) {
      onChange([...value, name])
    }
    setDraft('')
    setAdding(false)
  }

  return (
    <div className="flex flex-col gap-2">
      <Label>{label}</Label>
      <div className="flex flex-wrap items-center gap-2">
        {value.map((name) => (
          <Badge key={name} variant="secondary" className="gap-1 py-1">
            {name}
            <button
              type="button"
              aria-label={`删除 ${name}`}
              className="text-muted-foreground hover:text-foreground"
              onClick={() => onChange(value.filter((item) => item !== name))}
            >
              <X className="size-3" />
            </button>
          </Badge>
        ))}
        {!adding && (
          <Button type="button" variant="outline" size="sm" onClick={() => setAdding(true)}>
            添加
          </Button>
        )}
      </div>
      {adding && (
        <div className="flex items-center gap-2">
          <Input
            aria-label="新参数名"
            value={draft}
            placeholder="参数名"
            onChange={(event) => setDraft(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault()
                confirm()
              }
            }}
          />
          <Button type="button" size="sm" onClick={confirm}>
            确定
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => {
              setAdding(false)
              setDraft('')
            }}
          >
            取消
          </Button>
        </div>
      )}
    </div>
  )
}
