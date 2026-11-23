import { useEffect, useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Blocks, FolderKanban, LogOut, Menu, Settings, ShieldCheck, Ticket } from 'lucide-react'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import { cn } from '@/lib/utils'
import { Button, buttonVariants } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { ThemeToggle } from '@/components/theme-toggle'
import { toast } from 'sonner'
import { BrandMark } from '@/components/layout/brand-mark'

function NavLinks({ isAdmin }: { isAdmin: boolean }) {
  const location = useLocation()
  const items = [
    { to: '/', label: '我的项目', icon: FolderKanban },
    { to: '/modules', label: '模块库', icon: Blocks },
    ...(isAdmin ? [{ to: '/admin', label: '管理后台', icon: ShieldCheck }] : []),
  ]
  return (
    <div className="flex flex-col gap-2">
      <span className="px-3 text-[10px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
        Workspace
      </span>
      <nav className="flex flex-col gap-1">
        {items.map((item) => {
          const active = location.pathname === item.to
          return (
            <Link
              key={item.to}
              to={item.to}
              className={cn(
                'group flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm transition-colors',
                active
                  ? 'bg-primary/10 font-medium text-primary ring-1 ring-primary/15'
                  : 'text-muted-foreground hover:bg-muted hover:text-foreground',
              )}
            >
              <item.icon
                className={cn(
                  'size-4 transition-colors',
                  active ? 'text-primary' : 'text-muted-foreground group-hover:text-foreground',
                )}
              />
              {item.label}
              {active && <span className="ml-auto size-1.5 rounded-full bg-primary" />}
            </Link>
          )
        })}
      </nav>
    </div>
  )
}

function SidebarNote() {
  return (
    <div className="rounded-xl border border-primary/15 bg-primary/[0.04] p-3">
      <div className="flex items-center gap-2 text-xs font-medium text-primary">
        <ShieldCheck className="size-4" />
        仅限授权测试
      </div>
      <p className="mt-1.5 text-xs leading-relaxed text-muted-foreground">
        请确认已获得目标系统的明确授权，仅用于合法的安全测试与教学研究，并遵守当地法律法规。
      </p>
      <p className="mt-2.5 border-t border-primary/10 pt-2.5 text-[11px] leading-relaxed text-muted-foreground/80">
        作者 汤青松 · 公众号 青松阁主 · 微信 songboy8888
      </p>
    </div>
  )
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const { data } = useSession()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const isAdmin = (data?.user?.adminLevel ?? 0) > 0

  useEffect(() => {
    const onUnauthorized = () => {
      queryClient.invalidateQueries({ queryKey: ['session'] })
      if (window.location.pathname !== '/login') navigate('/login')
    }
    window.addEventListener('xssp:unauthorized', onUnauthorized)
    return () => window.removeEventListener('xssp:unauthorized', onUnauthorized)
  }, [navigate, queryClient])

  const logout = useMutation({
    mutationFn: () => api('logout', { method: 'POST', csrf: data?.csrf }),
    onSuccess: async () => {
      await queryClient.invalidateQueries()
      navigate('/login')
    },
  })

  const { search, pathname } = useLocation()
  useEffect(() => {
    if (!data?.installed) return
    const params = new URLSearchParams(search)
    const ok = params.get('ok')
    const error = params.get('error')
    if (ok) toast.success('操作成功')
    if (error) toast.error('操作失败：' + error)
  }, [search, data?.installed])

  if (data && data.installed === false && pathname !== '/install') {
    return <Navigate to="/install" replace />
  }

  const authed = Boolean(data?.user)
  const userName = data?.user?.userName ?? ''

  return (
    <div className="app-surface flex min-h-svh flex-col">
      <header
        className={cn(
          'sticky top-0 z-40 border-b border-border/70 bg-background/80 backdrop-blur-md',
          !authed && 'hidden',
        )}
      >
        <div className="mx-auto flex h-16 w-full max-w-7xl items-center gap-3 px-4 sm:px-6">
          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger
              className={cn(buttonVariants({ variant: 'ghost', size: 'icon' }), 'md:hidden')}
              aria-label="打开菜单"
            >
              <Menu />
            </SheetTrigger>
            <SheetContent side="left" className="w-72">
              <SheetTitle className="px-2 text-sm">导航</SheetTitle>
              <div className="flex flex-col gap-6 px-2" onClick={() => setOpen(false)}>
                <NavLinks isAdmin={isAdmin} />
                <div className="flex flex-col gap-1 border-t border-border/60 pt-4">
                  <Link
                    to="/settings"
                    className="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
                  >
                    <Settings className="size-4" />
                    个人设置
                  </Link>
                  {isAdmin && (
                    <Link
                      to="/invites"
                      className="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                      <Ticket className="size-4" />
                      邀请码
                    </Link>
                  )}
                </div>
                <SidebarNote />
              </div>
            </SheetContent>
          </Sheet>

          <Link to="/" className="flex shrink-0 items-center gap-3">
            <BrandMark />
            <div className="flex flex-col leading-none">
              <span className="text-sm font-semibold tracking-tight whitespace-nowrap">
                {data?.config.sitename ?? 'XSS平台'}
              </span>
              <span className="mt-1 hidden text-[9px] font-medium tracking-[0.22em] text-muted-foreground uppercase sm:block">
                Security Workbench
              </span>
            </div>
          </Link>

          <div className="ml-auto flex items-center gap-1.5">
            <ThemeToggle />
            <span className="hidden items-center gap-2 rounded-full border border-border/70 bg-card px-2 py-1 sm:flex">
              <span className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary">
                {userName.slice(0, 1).toUpperCase()}
              </span>
              <span className="pr-1 text-xs font-medium text-muted-foreground">{userName}</span>
            </span>
            <Link
              to="/settings"
              aria-label="个人设置"
              className={cn(buttonVariants({ variant: 'ghost', size: 'icon' }), 'hidden sm:inline-flex')}
            >
              <Settings />
            </Link>
            {isAdmin && (
              <>
                <Link
                  to="/invites"
                  aria-label="邀请"
                  className={cn(buttonVariants({ variant: 'ghost', size: 'icon' }), 'hidden sm:inline-flex')}
                >
                  <Ticket />
                </Link>
                <Link
                  to="/admin"
                  aria-label="管理后台"
                  className={cn(buttonVariants({ variant: 'ghost', size: 'icon' }), 'hidden sm:inline-flex')}
                >
                  <ShieldCheck />
                </Link>
              </>
            )}
            <span className="mx-1 hidden h-8 w-px bg-border sm:block" />
            <Button
              variant="ghost"
              size="icon"
              aria-label="退出登陆"
              onClick={() => logout.mutate()}
              disabled={logout.isPending}
            >
              <LogOut />
            </Button>
          </div>
        </div>
      </header>

      <div
        className={cn(
          'flex w-full flex-1',
          authed
            ? 'mx-auto max-w-7xl gap-6 px-4 py-6 sm:px-6 sm:py-8'
            : 'flex-col',
        )}
      >
        <aside className={cn('w-56 shrink-0 flex-col gap-6', authed ? 'hidden md:flex' : 'hidden')}>
          <NavLinks isAdmin={isAdmin} />
          <div className="mt-2">
            <SidebarNote />
          </div>
        </aside>
        <Separator orientation="vertical" className={cn(authed ? 'hidden md:block' : 'hidden')} />
        <main className="min-w-0 flex-1">{children}</main>
      </div>
    </div>
  )
}
