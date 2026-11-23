import { Crosshair } from 'lucide-react'
import { cn } from '@/lib/utils'

export function BrandMark({ className }: { className?: string }) {
  return (
    <span
      className={cn(
        'flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary via-primary to-indigo-400 text-white shadow-[0_8px_20px_-8px_rgba(79,70,229,0.7)] ring-1 ring-white/20',
        className,
      )}
      aria-hidden
    >
      <Crosshair className="size-5" />
    </span>
  )
}
