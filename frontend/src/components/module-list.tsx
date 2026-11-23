import { Link } from 'react-router-dom'
import { ChevronRight } from 'lucide-react'
import type { ModuleSummary } from '@/lib/types'
import { Badge } from '@/components/ui/badge'
import { EmptyState } from '@/components/empty-state'

export function ModuleList({
  modules,
  href,
  emptyTitle,
  emptyDescription,
}: {
  modules: ModuleSummary[]
  href: (module: ModuleSummary) => string
  emptyTitle: string
  emptyDescription: string
}) {
  if (modules.length === 0) {
    return <EmptyState title={emptyTitle} description={emptyDescription} />
  }

  return (
    <ul className="grid gap-2 sm:grid-cols-2">
      {modules.map((module) => (
        <li key={module.id}>
          <Link
            to={href(module)}
            className="group flex items-center justify-between gap-3 rounded-lg border bg-background px-3 py-2.5 transition-colors hover:border-primary/40 hover:bg-accent/50"
          >
            <div className="flex min-w-0 flex-col gap-0.5">
              <span className="flex items-center gap-2 text-sm font-medium">
                <span className="truncate">{module.title}</span>
                {module.isOpen === 1 && module.isAudit === 0 && (
                  <Badge variant="outline">待审核</Badge>
                )}
              </span>
              <span className="line-clamp-1 text-xs text-muted-foreground">
                {module.description || '暂无描述'}
              </span>
            </div>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
          </Link>
        </li>
      ))}
    </ul>
  )
}
