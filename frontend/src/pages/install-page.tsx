import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '@/lib/api'
import { agreementText } from '@/lib/agreement'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Check, CheckCircle2 } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface InstallStatus {
  installed: boolean
  configWritable: boolean
  phpVersion: string
  extensions: Record<string, boolean>
}

export function InstallPage() {
  const [status, setStatus] = useState<InstallStatus | null>(null)
  const [step, setStep] = useState(1)
  const [form, setForm] = useState({
    dbHost: '127.0.0.1',
    dbUser: 'root',
    dbPwd: '',
    dbName: 'xssplatform',
    username: '',
    password: '',
  })
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [done, setDone] = useState(false)
  const [pending, setPending] = useState(false)

  useEffect(() => {
    api<InstallStatus>('install.status')
      .then(setStatus)
      .catch((err) => setError(err instanceof Error ? err.message : '读取安装状态失败'))
  }, [])

  const ready = Boolean(
    status &&
      status.configWritable &&
      status.extensions.mysqli &&
      status.extensions.curl &&
      status.extensions.openssl,
  )

  function update(field: keyof typeof form, value: string) {
    setForm((prev) => ({ ...prev, [field]: value }))
  }

  async function testConnection() {
    setError('')
    setMessage('')
    setPending(true)
    try {
      await api('install.testDb', { method: 'POST', body: form })
      setMessage('数据库连接成功')
    } catch (err) {
      setError(err instanceof Error ? err.message : '数据库信息有误')
    } finally {
      setPending(false)
    }
  }

  async function submit() {
    setError('')
    setMessage('')
    setPending(true)
    try {
      await api('install.submit', { method: 'POST', body: form })
      setDone(true)
    } catch (err) {
      setError(err instanceof Error ? err.message : '安装失败')
    } finally {
      setPending(false)
    }
  }

  if (status?.installed) {
    return (
      <div className="flex min-h-[calc(100svh-10rem)] items-center justify-center">
        <Card className="w-full max-w-md text-center">
          <CardContent className="flex flex-col items-center gap-3 py-6">
            <span className="flex size-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">
              <CheckCircle2 className="size-6" />
            </span>
            <div className="flex flex-col gap-1">
              <h2 className="text-lg font-semibold">已安装</h2>
              <p className="text-sm text-muted-foreground">程序已经安装完成，无需重复安装。</p>
            </div>
            <Link to="/" className="mt-2 text-sm font-medium text-primary hover:underline">
              进入控制台
            </Link>
          </CardContent>
        </Card>
      </div>
    )
  }

  const steps = ['阅读协议', '数据库', '完成安装']

  return (
    <div className="mx-auto flex min-h-[calc(100svh-6rem)] w-full max-w-3xl flex-col justify-center py-8">
      <Card>
        <CardHeader className="gap-4">
          <div className="flex flex-col gap-1">
            <CardTitle>安装向导</CardTitle>
            <CardDescription>几步完成部署，生产环境不需要 Node.js。</CardDescription>
          </div>
          <ol className="flex items-center gap-2">
            {steps.map((label, index) => {
              const number = index + 1
              const done = step > number
              const active = step === number
              return (
                <li key={label} className="flex flex-1 items-center gap-2">
                  <span
                    className={[
                      'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                      active
                        ? 'bg-primary text-primary-foreground'
                        : done
                          ? 'bg-primary/15 text-primary'
                          : 'bg-muted text-muted-foreground',
                    ].join(' ')}
                  >
                    {done ? <Check className="size-3.5" /> : number}
                  </span>
                  <span className={active ? 'text-xs font-medium' : 'text-xs text-muted-foreground'}>
                    {label}
                  </span>
                  {index < steps.length - 1 && <span className="h-px flex-1 bg-border" />}
                </li>
              )
            })}
          </ol>
          <div className="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
            <span>PHP {status?.phpVersion ?? '...'}</span>
            <span>mysqli {status?.extensions.mysqli ? '可用' : '缺失'}</span>
            <span>curl {status?.extensions.curl ? '可用' : '缺失'}</span>
            <span>openssl {status?.extensions.openssl ? '可用' : '缺失'}</span>
          </div>
        </CardHeader>
        <CardContent className="flex flex-col gap-5">
          {step === 1 && (
            <>
              <div className="max-h-80 overflow-auto rounded-lg border p-4 text-sm leading-relaxed">
                {agreementText.map((paragraph, index) => (
                  <p key={index} className="mb-3">
                    {paragraph}
                  </p>
                ))}
              </div>
              {!status?.configWritable && (
                <p className="text-sm text-destructive">config.php 不可写，无法继续安装</p>
              )}
              {error && <p className="text-sm text-destructive">{error}</p>}
              <div className="flex justify-end border-t border-border/60 pt-4">
                <Button disabled={!ready} onClick={() => setStep(2)}>
                  我同意此协议
                </Button>
              </div>
            </>
          )}

          {step === 2 && (
            <>
              <section className="flex flex-col gap-4">
                <h3 className="text-[11px] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                  数据库
                </h3>
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="db-host">数据库地址</Label>
                    <Input id="db-host" className="h-10" value={form.dbHost} onChange={(e) => update('dbHost', e.target.value)} />
                  </div>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="db-name">数据库名称</Label>
                    <Input id="db-name" className="h-10" value={form.dbName} onChange={(e) => update('dbName', e.target.value)} />
                  </div>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="db-user">数据库用户名</Label>
                    <Input id="db-user" className="h-10" value={form.dbUser} onChange={(e) => update('dbUser', e.target.value)} />
                  </div>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="db-pwd">数据库密码</Label>
                    <Input
                      id="db-pwd"
                      className="h-10"
                      type="password"
                      value={form.dbPwd}
                      onChange={(e) => update('dbPwd', e.target.value)}
                    />
                  </div>
                </div>
              </section>
              <section className="flex flex-col gap-4 border-t border-border/60 pt-5">
                <h3 className="text-[11px] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                  管理员账号
                </h3>
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="admin-user">管理员</Label>
                    <Input
                      id="admin-user"
                      className="h-10"
                      value={form.username}
                      onChange={(e) => update('username', e.target.value)}
                      placeholder="admin"
                    />
                  </div>
                  <div className="flex flex-col gap-2">
                    <Label htmlFor="admin-pwd">管理员密码</Label>
                    <Input
                      id="admin-pwd"
                      className="h-10"
                      type="password"
                      value={form.password}
                      onChange={(e) => update('password', e.target.value)}
                      placeholder="6-20 位字符"
                    />
                  </div>
                </div>
              </section>
              {message && <p className="text-sm text-emerald-600">{message}</p>}
              {error && <p className="text-sm text-destructive">{error}</p>}
              <div className="flex items-center gap-2 border-t border-border/60 pt-4">
                <Button variant="outline" onClick={() => setStep(1)}>
                  上一步
                </Button>
                <Button variant="outline" disabled={pending} onClick={testConnection}>
                  测试连接
                </Button>
                <Button
                  disabled={pending || form.username === '' || form.password === ''}
                  onClick={() => setStep(3)}
                >
                  下一步
                </Button>
              </div>
            </>
          )}

          {step === 3 && (
            <>
              {!done ? (
                <>
                  <p className="text-sm text-muted-foreground">
                    将创建数据库、导入初始数据并把管理员账号写入第一位用户。
                  </p>
                  {error && <p className="text-sm text-destructive">{error}</p>}
                  <div className="flex items-center gap-2 border-t border-border/60 pt-4">
                    <Button variant="outline" onClick={() => setStep(2)}>
                      上一步
                    </Button>
                    <Button disabled={pending} onClick={submit}>
                      开始安装
                    </Button>
                  </div>
                </>
              ) : (
                <>
                  <p className="text-sm">导入数据成功！</p>
                  <Link to="/login" className="text-sm underline">
                    进入登录
                  </Link>
                </>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
