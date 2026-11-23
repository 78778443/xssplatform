import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PageHeader } from '@/components/layout/page-header'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

interface Invite {
  id: number
  code: string
  addTime: number
}

interface InvitesData {
  wooyun: Invite[]
  other: Invite[]
}

function InviteTable({
  title,
  invites,
  onGenerate,
}: {
  title: string
  invites: Invite[]
  onGenerate: () => void
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle>
          {title}（{invites.length}）
        </CardTitle>
        <Button size="sm" variant="outline" onClick={onGenerate}>
          生成邀请码
        </Button>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>邀请码（生成时间倒序排列）</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {invites.map((invite) => (
              <TableRow key={invite.id}>
                <TableCell className="font-mono">{invite.code}</TableCell>
              </TableRow>
            ))}
            {invites.length === 0 && (
              <TableRow>
                <TableCell className="text-center text-muted-foreground">暂无未使用的邀请码</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  )
}

export function InvitesPage() {
  const { data: session } = useSession()
  const queryClient = useQueryClient()
  const query = useQuery({
    queryKey: ['invites'],
    queryFn: () => api<InvitesData>('user.invites'),
  })

  const create = useMutation({
    mutationFn: (isWooyun: number) =>
      api('user.invite.create', { method: 'POST', csrf: session?.csrf, body: { isWooyun } }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['invites'] })
      toast.success('操作成功')
    },
    onError: (error) => toast.error(error instanceof Error ? error.message : '操作失败'),
  })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        eyebrow="Invitations"
        title="邀请码"
        description="生成并管理注册邀请码，邀请码模式下新用户必须持码注册。"
      />
      <InviteTable
        title="乌云币奖品邀请码"
        invites={query.data?.wooyun ?? []}
        onGenerate={() => create.mutate(1)}
      />
      <InviteTable
        title="其它邀请码"
        invites={query.data?.other ?? []}
        onGenerate={() => create.mutate(0)}
      />
    </div>
  )
}
