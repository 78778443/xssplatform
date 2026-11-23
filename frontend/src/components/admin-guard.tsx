import { useSession } from '@/lib/session'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export function AdminGuard({ children }: { children: React.ReactNode }) {
  const { data } = useSession()
  if (!data?.user) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>未登录</CardTitle>
        </CardHeader>
        <CardContent className="text-sm text-muted-foreground">请先登录后访问管理后台。</CardContent>
      </Card>
    )
  }
  if (data.user.adminLevel <= 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>没有操作权限</CardTitle>
        </CardHeader>
        <CardContent className="text-sm text-muted-foreground">当前账号不是管理员。</CardContent>
      </Card>
    )
  }
  return <>{children}</>
}
