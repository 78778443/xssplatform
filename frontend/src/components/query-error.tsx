import { AlertTriangle } from 'lucide-react'

export function QueryError({ error }: { error: unknown }) {
  const message = error instanceof Error ? error.message : '加载失败，请稍后重试'
  return (
    <div className="flex items-center gap-3 rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
      <AlertTriangle className="size-4 shrink-0" />
      {message}
    </div>
  )
}
