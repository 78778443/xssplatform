import { Button } from '@/components/ui/button'
import { Code2, Copy } from 'lucide-react'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'

export function CodeBlock({
  label,
  value,
  className,
}: {
  label: string
  value: string
  className?: string
}) {
  async function copy() {
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value)
      } else {
        const area = document.createElement('textarea')
        area.value = value
        area.style.position = 'fixed'
        area.style.opacity = '0'
        document.body.appendChild(area)
        area.select()
        const ok = document.execCommand('copy')
        document.body.removeChild(area)
        if (!ok) throw new Error('copy failed')
      }
      toast.success('已复制')
    } catch {
      toast.error('复制失败，请手动选择文本')
    }
  }

  return (
    <div className={cn('overflow-hidden rounded-xl border border-border/60 bg-card', className)}>
      <div className="flex h-11 items-center justify-between gap-2 border-b border-border/60 bg-muted/25 px-3.5">
        <span className="flex items-center gap-2 text-xs font-semibold text-foreground">
          <span className="flex size-6 items-center justify-center rounded-md bg-primary/10 text-primary">
            <Code2 className="size-3.5" />
          </span>
          {label}
        </span>
        <Button variant="ghost" size="sm" className="h-7 px-2 text-xs text-muted-foreground" onClick={copy}>
          <Copy className="size-3.5" />
          复制
        </Button>
      </div>
      <pre className="max-h-72 overflow-auto bg-card p-4 font-mono text-xs leading-relaxed break-all whitespace-pre-wrap">
        <code>{value}</code>
      </pre>
    </div>
  )
}
