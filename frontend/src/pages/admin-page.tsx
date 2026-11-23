import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
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

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm text-muted-foreground">{label}</CardTitle>
      </CardHeader>
      <CardContent className="text-2xl font-semibold">{value}</CardContent>
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
        <div className="grid gap-4 sm:grid-cols-2">
          <Stat label="注册用户总数" value={query.data?.userCount ?? '-'} />
          <Stat label="在线用户总数" value={query.data?.onlineCount ?? '-'} />
        </div>

        <Card>
          <CardHeader className="flex-row items-center justify-between">
            <CardTitle>在线用户</CardTitle>
            <Link to="/admin/modules" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
              模块审核
            </Link>
          </CardHeader>
          <CardContent className="text-sm">
            {query.data?.onlineUsers.length ? (
              <ul className="flex flex-col gap-1">
                {query.data.onlineUsers.map((user) => (
                  <li key={user.id}>{user.userName}</li>
                ))}
              </ul>
            ) : (
              <p className="text-muted-foreground">暂无在线用户</p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>系统环境</CardTitle>
          </CardHeader>
          <CardContent>
            <dl className="grid gap-2 text-sm sm:grid-cols-2">
              {Object.entries(query.data?.sysInfo ?? {}).map(([key, value]) => (
                <div key={key} className="flex gap-2">
                  <dt className="text-muted-foreground">{key}:</dt>
                  <dd className="font-mono break-all">{value}</dd>
                </div>
              ))}
            </dl>
          </CardContent>
        </Card>
      </div>
    </AdminGuard>
  )
}
