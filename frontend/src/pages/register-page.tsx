import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { registerSchema } from '@/lib/validation'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface CheckResult {
  userExisted: number
  emailExisted: number
  keyError: number
}

export function RegisterPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [params] = useSearchParams()
  const [key, setKey] = useState(params.get('key') ?? '')
  const [user, setUser] = useState('')
  const [email, setEmail] = useState('')
  const [pwd, setPwd] = useState('')
  const [pwd2, setPwd2] = useState('')
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [pending, setPending] = useState(false)

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setError('')
    setFieldErrors({})
    const parsed = registerSchema.safeParse({ key, user, email, pwd, pwd2 })
    if (!parsed.success) {
      const errors: Record<string, string> = {}
      for (const issue of parsed.error.issues) {
        const field = String(issue.path[0] ?? '')
        errors[field] = issue.message
      }
      setFieldErrors(errors)
      return
    }

    setPending(true)
    try {
      const check = await api<CheckResult>('register.check', {
        method: 'POST',
        body: { user, email, key },
      })
      const errors: Record<string, string> = {}
      if (check.userExisted > 0) errors.user = '用户已存在'
      if (check.emailExisted > 0) errors.email = '邮箱已存在'
      if (check.keyError > 0) errors.key = '邀请码输入错误'
      if (Object.keys(errors).length > 0) {
        setFieldErrors(errors)
        return
      }
      await api('register.submit', {
        method: 'POST',
        body: { user, email, pwd, key },
      })
      await queryClient.invalidateQueries({ queryKey: ['session'] })
      navigate('/')
    } catch (err) {
      setError(err instanceof Error ? err.message : '注册失败')
    } finally {
      setPending(false)
    }
  }

  return (
    <div className="flex min-h-[calc(100svh-8rem)] items-center justify-center">
      <div className="w-full max-w-xl">
      <Card>
        <CardHeader>
          <CardTitle>注册</CardTitle>
          <CardDescription>注册后会自动登录</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="grid gap-4" onSubmit={submit}>
            <div className="flex flex-col gap-2">
              <Label htmlFor="reg-user">用户名</Label>
              <Input
                id="reg-user"
                value={user}
                onChange={(e) => setUser(e.target.value)}
                placeholder="4-20个字符(字母、汉字、数字、下划线)"
                autoComplete="username"
              />
              {fieldErrors.user && <p className="text-sm text-destructive">{fieldErrors.user}</p>}
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="reg-email">邮箱</Label>
              <Input
                id="reg-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="可以使用邮箱登录"
                autoComplete="email"
              />
              {fieldErrors.email && <p className="text-sm text-destructive">{fieldErrors.email}</p>}
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="reg-pwd">密码</Label>
              <Input
                id="reg-pwd"
                type="password"
                value={pwd}
                onChange={(e) => setPwd(e.target.value)}
                placeholder="6-20个字符"
                autoComplete="new-password"
              />
              {fieldErrors.pwd && <p className="text-sm text-destructive">{fieldErrors.pwd}</p>}
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="reg-pwd2">确认密码</Label>
              <Input
                id="reg-pwd2"
                type="password"
                value={pwd2}
                onChange={(e) => setPwd2(e.target.value)}
                placeholder="请再次输入密码"
                autoComplete="new-password"
              />
              {fieldErrors.pwd2 && <p className="text-sm text-destructive">{fieldErrors.pwd2}</p>}
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="reg-key">邀请码</Label>
              <Input
                id="reg-key"
                value={key}
                onChange={(e) => setKey(e.target.value)}
                placeholder="请输入邀请码"
              />
              {fieldErrors.key && <p className="text-sm text-destructive">{fieldErrors.key}</p>}
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            <Button type="submit" disabled={pending}>
              注册
            </Button>
            <div className="text-center text-sm text-muted-foreground">
              已有账号？<Link to="/login">登录</Link>
            </div>
          </form>
        </CardContent>
      </Card>
      </div>
    </div>
  )
}
