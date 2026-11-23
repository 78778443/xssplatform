import { PackageSearch, ShieldCheck, Waypoints } from 'lucide-react'
import { BrandMark } from '@/components/layout/brand-mark'

const highlights = [
  { icon: PackageSearch, title: '实时收包', text: '页面地址、Cookie、请求头与自定义字段一次回传' },
  { icon: Waypoints, title: '模块化 Payload', text: '按字段组合模块，私有模块与公共模块并存' },
  { icon: ShieldCheck, title: '授权测试', text: '登录、项目与后台审核全部围绕授权目标展开' },
]

export function AuthLayout({
  title,
  description,
  children,
  footer,
}: {
  title: string
  description: string
  children: React.ReactNode
  footer?: React.ReactNode
}) {
  return (
    <div className="app-surface flex min-h-svh items-center justify-center px-4 py-8 sm:py-12">
      <div className="grid w-full max-w-5xl overflow-hidden rounded-3xl border border-border/70 bg-card shadow-[0_30px_80px_-40px_rgba(15,23,42,0.45)] lg:grid-cols-[1.05fr_0.95fr]">
        <aside className="auth-hero relative hidden flex-col justify-between overflow-hidden p-10 lg:flex">
          <div className="auth-grid pointer-events-none absolute inset-0" />
          <div className="relative flex items-center gap-3">
            <BrandMark className="size-10" />
            <div className="flex flex-col">
              <span className="text-base font-semibold tracking-tight text-foreground">XSS Platform</span>
              <span className="text-[10px] tracking-[0.2em] text-primary uppercase">
                Security Workbench
              </span>
            </div>
          </div>

          <div className="relative flex flex-col gap-7 py-12">
            <div className="flex flex-col gap-3">
              <span className="text-[11px] font-semibold tracking-[0.22em] text-primary uppercase">
                Authorized testing only
              </span>
              <h2 className="max-w-sm text-3xl leading-tight font-semibold tracking-tight text-foreground">
                把每一次 XSS 验证，<br />收进同一个工作台。
              </h2>
              <p className="max-w-sm text-sm leading-relaxed text-muted-foreground">
                项目、模块、收包内容与审核流程集中在一处，验证过程清晰可查。
              </p>
            </div>

            <ul className="flex flex-col gap-4">
              {highlights.map((item) => (
                <li key={item.title} className="flex items-start gap-3">
                  <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-card/80 text-primary ring-1 ring-primary/15 dark:bg-white/10 dark:text-white dark:ring-white/15">
                    <item.icon className="size-4" />
                  </span>
                  <div className="flex flex-col">
                    <span className="text-sm font-medium text-foreground">{item.title}</span>
                    <span className="text-xs leading-relaxed text-muted-foreground">{item.text}</span>
                  </div>
                </li>
              ))}
            </ul>
          </div>

          <p className="relative text-xs text-muted-foreground">
            仅面向已获授权的安全测试目标，请遵守当地法律法规。
          </p>
        </aside>

        <section className="flex flex-col justify-center px-6 py-8 sm:px-10 sm:py-12">
          <div className="mb-8 flex flex-col gap-2 lg:mb-10">
            <div className="mb-2 flex items-center gap-3 lg:hidden">
              <BrandMark />
              <span className="text-base font-semibold tracking-tight text-foreground">XSS Platform</span>
            </div>
            <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
            <p className="text-sm text-muted-foreground">{description}</p>
          </div>
          {children}
          {footer && <div className="mt-6 text-center text-sm text-muted-foreground">{footer}</div>}
        </section>
      </div>
    </div>
  )
}
