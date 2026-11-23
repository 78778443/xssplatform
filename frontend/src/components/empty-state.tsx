import { Inbox } from 'lucide-react'

export function EmptyState({
  title,
  description,
  icon,
}: {
  title: string
  description?: string
  icon?: React.ReactNode
}) {
  return (
    <div className="m-1 flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/80 bg-muted/20 px-6 py-12 text-center">
      <div className="flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary">
        {icon ?? <Inbox className="size-5" />}
      </div>
      <p className="text-sm font-semibold">{title}</p>
      {description && <p className="max-w-sm text-sm text-muted-foreground">{description}</p>}
    </div>
  )
}
