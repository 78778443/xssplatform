import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { LogOut, Menu, Settings, Shield, Ticket } from 'lucide-react'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import { cn } from '@/lib/utils'
import { Button, buttonVariants } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { ThemeToggle } from '@/components/theme-toggle'

function NavLinks({ isAdmin }: { isAdmin: boolean }) {
  const location = useLocation()
  const items = [
    { to: '/', label: '我的项目' },
    { to: '/modules', label: '我的模块 / 公共模块' },
    ...(isAdmin ? [{ to: '/admin', label: '管理后台' }] : []),
  ]
  return (
    <nav className="flex flex-col gap-1">
      {items.map((item) => (
        <Link
          key={item.to}
          to={item.to}
          className={cn(
            buttonVariants({
              variant: location.pathname === item.to ? 'secondary' : 'ghost',
            }),
            'justify-start',
          )}
        >
          {item.label}
        </Link>
      ))}
    </nav>
  )
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const { data } = useSession()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const isAdmin = (data?.user?.adminLevel ?? 0) > 0

  const logout = useMutation({
    mutationFn: () => api('logout', { method: 'POST', csrf: data?.csrf }),
    onSuccess: async () => {
      await queryClient.invalidateQueries()
      navigate('/login')
    },
  })

  return (
    <div className="flex min-h-svh flex-col">
      <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
        <div className="mx-auto flex h-14 w-full max-w-6xl items-center gap-3 px-4">
          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger
              className={cn(buttonVariants({ variant: 'ghost', size: 'icon' }), 'md:hidden')}
              aria-label="打开菜单"
            >
              <Menu />
            </SheetTrigger>
            <SheetContent side="left" className="w-64">
              <SheetTitle className="px-2 text-sm">导航</SheetTitle>
              <div className="px-2" onClick={() => setOpen(false)}>
                <NavLinks isAdmin={isAdmin} />
              </div>
            </SheetContent>
          </Sheet>

          <Link to="/" className="font-semibold tracking-tight">
            {data?.config.sitename ?? 'XSS平台'}
          </Link>

          <div className="ml-auto flex items-center gap-2">
            <ThemeToggle />
            {data?.user ? (
              <>
                <span className="hidden text-sm text-muted-foreground sm:inline">
                  {data.user.userName}
                </span>
                <Link
                  to="/settings"
                  aria-label="个人设置"
                  className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                >
                  <Settings />
                </Link>
                {isAdmin && (
                  <>
                    <Link
                      to="/invites"
                      aria-label="邀请"
                      className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                    >
                      <Ticket />
                    </Link>
                    <Link
                      to="/admin"
                      aria-label="管理后台"
                      className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                    >
                      <Shield />
                    </Link>
                  </>
                )}
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label="退出登陆"
                  onClick={() => logout.mutate()}
                  disabled={logout.isPending}
                >
                  <LogOut />
                </Button>
              </>
            ) : (
              <>
                <Link to="/login" className={buttonVariants({ variant: 'ghost' })}>
                  登录
                </Link>
                {data?.config.register !== 'close' && (
                  <Link to="/register" className={buttonVariants()}>
                    注册
                  </Link>
                )}
              </>
            )}
          </div>
        </div>
      </header>

      <div className="mx-auto flex w-full max-w-6xl flex-1 gap-6 px-4 py-6">
        <aside className="hidden w-56 shrink-0 md:block">
          <NavLinks isAdmin={isAdmin} />
        </aside>
        <Separator orientation="vertical" className="hidden md:block" />
        <main className="min-w-0 flex-1">{children}</main>
      </div>
    </div>
  )
}
