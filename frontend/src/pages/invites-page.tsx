import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PageHeader } from '@/components/layout/page-header'
import { Badge } from '@/components/ui/badge'
import { EmptyState } from '@/components/empty-state'
import { Copy } from 'lucide-react'
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

function formatTime(value: number) {
  const date = new Date(value * 1000)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

async function copyCode(code: string) {
  try {
    await navigator.clipboard.writeText(code)
    toast.success('已复制')
  } catch {
    toast.error('复制失败，请手动选择')
  }
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
        <div className="flex items-center gap-2">
          <CardTitle>{title}</CardTitle>
          <Badge variant="secondary">{invites.length}</Badge>
        </div>
        <Button size="sm" onClick={onGenerate}>
          生成邀请码
        </Button>
      </CardHeader>
      <CardContent>
        {invites.length === 0 ? (
          <EmptyState
            title="暂无未使用的邀请码"
            description="邀请码模式下，新用户必须持码才能注册。"
          />
        ) : (
          <Table>
            <TableHeader className="bg-muted/40">
              <TableRow>
                <TableHead>邀请码</TableHead>
                <TableHead className="w-44">生成时间</TableHead>
                <TableHead className="w-20">操作</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {invites.map((invite) => (
                <TableRow key={invite.id}>
                  <TableCell>
                    <code className="rounded bg-muted px-2 py-1 font-mono text-xs">{invite.code}</code>
                  </TableCell>
                  <TableCell className="text-xs text-muted-foreground">{formatTime(invite.addTime)}</TableCell>
                  <TableCell>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="px-2 text-xs"
                      onClick={() => copyCode(invite.code)}
                    >
                      <Copy className="size-3.5" />
                      复制
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
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
