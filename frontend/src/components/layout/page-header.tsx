export function PageHeader({
  eyebrow,
  title,
  description,
  actions,
}: {
  eyebrow?: string
  title: string
  description?: string
  actions?: React.ReactNode
}) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
      <div className="flex min-w-0 flex-col gap-2">
        {eyebrow && (
          <span className="flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] text-primary/80 uppercase">
            <span className="h-px w-6 bg-primary/40" aria-hidden />
            {eyebrow}
          </span>
        )}
        <h1 className="text-[28px] leading-tight font-semibold tracking-[-0.02em] text-foreground">
          {title}
        </h1>
        {description && (
          <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground">{description}</p>
        )}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2.5">{actions}</div>}
    </div>
  )
}
