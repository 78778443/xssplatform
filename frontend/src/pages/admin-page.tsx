import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Activity, Server, Users } from 'lucide-react'
import { PageHeader } from '@/components/layout/page-header'
import { api } from '@/lib/api'
import { AdminGuard } from '@/components/admin-guard'
import { buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface Overview {
  userCount: number
  onlineCount: number
  onlineUsers: Array<{ id: number; userName: string }>
  sysInfo: Record<string, string>
}

function Stat({
  label,
  value,
  icon,
}: {
  label: string
  value: string | number
  icon: React.ReactNode
}) {
  return (
    <Card>
      <CardContent className="flex items-center gap-4">
        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary/20 to-primary/5 text-primary ring-1 ring-primary/15">
          {icon}
        </div>
        <div className="flex flex-col">
          <span className="text-sm text-muted-foreground">{label}</span>
          <span className="text-3xl font-semibold tracking-tight">{value}</span>
        </div>
      </CardContent>
    </Card>
  )
}

export function AdminPage() {
  const query = useQuery({
    queryKey: ['admin-overview'],
    queryFn: () => api<Overview>('admin.overview'),
  })

  return (
    <AdminGuard>
      <div className="flex flex-col gap-6">
        <PageHeader
          eyebrow="Admin"
          title="管理后台"
          description="账号概况、在线状态、系统环境与公共模块审核。"
        />
        <div className="grid gap-4 sm:grid-cols-2">
          <Stat label="注册用户总数" value={query.data?.userCount ?? '-'} icon={<Users className="size-5" />} />
          <Stat label="在线用户总数" value={query.data?.onlineCount ?? '-'} icon={<Activity className="size-5" />} />
        </div>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>在线用户</CardTitle>
            <Link to="/admin/modules" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
              模块审核
            </Link>
          </CardHeader>
          <CardContent className="text-sm">
            {query.data?.onlineUsers.length ? (
              <ul className="flex flex-wrap gap-2">
                {query.data.onlineUsers.map((user) => (
                  <li
                    key={user.id}
                    className="flex items-center gap-2 rounded-full border bg-background py-1 pr-3 pl-1"
                  >
                    <span className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-xs font-medium text-primary">
                      {user.userName.slice(0, 1).toUpperCase()}
                    </span>
                    {user.userName}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-muted-foreground">暂无在线用户</p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center gap-2">
            <Server className="size-4 text-muted-foreground" />
            <CardTitle>系统环境</CardTitle>
          </CardHeader>
          <CardContent>
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              {Object.entries(query.data?.sysInfo ?? {}).map(([key, value]) => (
                <div key={key} className="flex flex-col gap-0.5 rounded-lg border bg-background px-3 py-2">
                  <dt className="text-xs text-muted-foreground">{key}</dt>
                  <dd className="font-mono text-xs break-all">{value}</dd>
                </div>
              ))}
            </dl>
          </CardContent>
        </Card>
      </div>
    </AdminGuard>
  )
}
