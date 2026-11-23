import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { loginSchema } from '@/lib/validation'
import { Button } from '@/components/ui/button'
import { AuthLayout } from '@/components/layout/auth-layout'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LockKeyhole, Mail } from 'lucide-react'

export function LoginPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [user, setUser] = useState('')
  const [pwd, setPwd] = useState('')
  const [auto, setAuto] = useState(false)
  const [error, setError] = useState('')
  const [pending, setPending] = useState(false)

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setError('')
    const parsed = loginSchema.safeParse({ user, pwd, auto })
    if (!parsed.success) {
      setError(parsed.error.issues[0]?.message ?? '请检查输入')
      return
    }
    setPending(true)
    try {
      await api('login', {
        method: 'POST',
        body: { user, pwd, auto: auto ? 1 : 0 },
      })
      await queryClient.invalidateQueries({ queryKey: ['session'] })
      navigate('/')
    } catch (err) {
      setError(err instanceof Error ? err.message : '登录失败')
    } finally {
      setPending(false)
    }
  }

  return (
    <AuthLayout
      title="登陆"
      description="使用用户名或邮箱登录"
      footer={
        <span>
          还没有账号？{' '}
          <Link to="/register" className="font-medium text-primary hover:underline">
            注册
          </Link>
        </span>
      }
    >
      <form className="flex flex-col gap-5" onSubmit={submit}>
        <div className="flex flex-col gap-2">
          <Label htmlFor="login-user">用户名或邮箱</Label>
          <div className="relative">
            <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id="login-user"
              className="h-10 pl-9"
              value={user}
              onChange={(e) => setUser(e.target.value)}
              placeholder="输入用户名/邮箱"
              autoComplete="username"
            />
          </div>
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="login-pwd">密码</Label>
          <div className="relative">
            <LockKeyhole className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id="login-pwd"
              className="h-10 pl-9"
              type="password"
              value={pwd}
              onChange={(e) => setPwd(e.target.value)}
              placeholder="输入密码"
              autoComplete="current-password"
            />
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Checkbox
            id="login-auto"
            checked={auto}
            onCheckedChange={(checked) => setAuto(checked === true)}
          />
          <Label htmlFor="login-auto" className="text-muted-foreground">
            自动登录（360 天）
          </Label>
        </div>
        {error && <p className="text-sm text-destructive">{error}</p>}
        <Button type="submit" size="lg" className="h-10 w-full" disabled={pending}>
          登录
        </Button>
      </form>
    </AuthLayout>
  )
}
