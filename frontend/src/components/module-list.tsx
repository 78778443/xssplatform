import { Link } from 'react-router-dom'
import { ChevronRight, Globe2, LockKeyhole } from 'lucide-react'
import { cn } from '@/lib/utils'
import type { ModuleSummary } from '@/lib/types'
import { Badge } from '@/components/ui/badge'
import { EmptyState } from '@/components/empty-state'

export function ModuleList({
  modules,
  href,
  emptyTitle,
  emptyDescription,
  columns = 2,
}: {
  modules: ModuleSummary[]
  href: (module: ModuleSummary) => string
  emptyTitle: string
  emptyDescription: string
  columns?: 1 | 2
}) {
  if (modules.length === 0) {
    return <EmptyState title={emptyTitle} description={emptyDescription} />
  }

  return (
    <ul className={cn('grid gap-2', columns === 2 && 'sm:grid-cols-2')}>
      {modules.map((module) => {
        const isPrivate = module.isOpen === 0 || module.isAudit === 0
        return (
          <li key={module.id}>
            <Link
              to={href(module)}
              className="group flex items-center gap-3 rounded-xl border border-border/70 bg-card px-3 py-3 transition-all hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
            >
              <span
                className={cn(
                  'flex size-9 shrink-0 items-center justify-center rounded-lg ring-1',
                  isPrivate
                    ? 'bg-amber-500/10 text-amber-600 ring-amber-500/15'
                    : 'bg-primary/10 text-primary ring-primary/15',
                )}
              >
                {isPrivate ? <LockKeyhole className="size-4" /> : <Globe2 className="size-4" />}
              </span>
              <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="flex items-center gap-2 text-sm font-semibold">
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
        )
      })}
    </ul>
  )
}
