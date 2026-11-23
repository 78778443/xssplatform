import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '@/lib/api'
import { agreementText } from '@/lib/agreement'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
      <div className="mx-auto max-w-xl py-10">
        <Card>
          <CardHeader>
            <CardTitle>已安装</CardTitle>
            <CardDescription>程序已经安装完成，无需重复安装</CardDescription>
          </CardHeader>
          <CardContent>
            <Link to="/">返回首页</Link>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl py-10">
      <Card>
        <CardHeader>
          <CardTitle>安装向导 · 第 {step} 步</CardTitle>
          <CardDescription>
            PHP {status?.phpVersion ?? '...'} · mysqli {status?.extensions.mysqli ? '可用' : '缺失'} · curl{' '}
            {status?.extensions.curl ? '可用' : '缺失'} · openssl {status?.extensions.openssl ? '可用' : '缺失'}
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
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
              <Button disabled={!ready} onClick={() => setStep(2)}>
                我同意此协议
              </Button>
            </>
          )}

          {step === 2 && (
            <>
              <div className="grid gap-4 md:grid-cols-2">
                <div className="flex flex-col gap-2">
                  <Label htmlFor="db-host">数据库地址</Label>
                  <Input id="db-host" value={form.dbHost} onChange={(e) => update('dbHost', e.target.value)} />
                </div>
                <div className="flex flex-col gap-2">
                  <Label htmlFor="db-name">数据库名称</Label>
                  <Input id="db-name" value={form.dbName} onChange={(e) => update('dbName', e.target.value)} />
                </div>
                <div className="flex flex-col gap-2">
                  <Label htmlFor="db-user">数据库用户名</Label>
                  <Input id="db-user" value={form.dbUser} onChange={(e) => update('dbUser', e.target.value)} />
                </div>
                <div className="flex flex-col gap-2">
                  <Label htmlFor="db-pwd">数据库密码</Label>
                  <Input
                    id="db-pwd"
                    type="password"
                    value={form.dbPwd}
                    onChange={(e) => update('dbPwd', e.target.value)}
                  />
                </div>
                <div className="flex flex-col gap-2">
                  <Label htmlFor="admin-user">管理员</Label>
                  <Input
                    id="admin-user"
                    value={form.username}
                    onChange={(e) => update('username', e.target.value)}
                  />
                </div>
                <div className="flex flex-col gap-2">
                  <Label htmlFor="admin-pwd">管理员密码</Label>
                  <Input
                    id="admin-pwd"
                    type="password"
                    value={form.password}
                    onChange={(e) => update('password', e.target.value)}
                  />
                </div>
              </div>
              {message && <p className="text-sm text-emerald-600">{message}</p>}
              {error && <p className="text-sm text-destructive">{error}</p>}
              <div className="flex gap-2">
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
                  <div className="flex gap-2">
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
