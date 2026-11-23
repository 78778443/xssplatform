import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import { AdminGuard } from '@/components/admin-guard'
import { EmptyState } from '@/components/empty-state'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Check, ChevronLeft, ChevronRight, RotateCcw } from 'lucide-react'
import { PageHeader } from '@/components/layout/page-header'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

interface AdminModule {
  id: number
  title: string
  description: string
  userName: string
  isAudit: number
}

interface AdminModulesData {
  items: AdminModule[]
  total: number
  pno: number
  pageSize: number
}

export function AdminModulesPage() {
  const { data: session } = useSession()
  const queryClient = useQueryClient()
  const [pno, setPno] = useState(1)
  const query = useQuery({
    queryKey: ['admin-modules', pno],
    queryFn: () => api<AdminModulesData>('admin.modules', { query: { pNO: String(pno) } }),
  })

  const audit = useMutation({
    mutationFn: (payload: { id: number; isAudit: number }) =>
      api('admin.module.audit', { method: 'POST', csrf: session?.csrf, body: payload }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-modules'] })
      toast.success('操作成功')
    },
    onError: (error) => toast.error(error instanceof Error ? error.message : '操作失败'),
  })

  const total = query.data?.total ?? 0
  const pages = Math.max(1, Math.ceil(total / (query.data?.pageSize ?? 20)))

  return (
    <AdminGuard>
      <div className="flex flex-col gap-6">
        <PageHeader
          eyebrow="Admin"
          title="模块审核"
          description="审核用户提交的公共模块，通过后会出现在所有用户的模块库中。"
        />
      <Card>
        <CardContent className="flex flex-col gap-4">
          <Table>
            <TableHeader className="bg-muted/40">
              <TableRow>
                <TableHead className="w-[28%]">模块名称</TableHead>
                <TableHead>描述</TableHead>
                <TableHead className="w-32">作者</TableHead>
                <TableHead className="w-24">状态</TableHead>
                <TableHead className="w-32 text-right">操作</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(query.data?.items ?? []).map((module) => (
                <TableRow key={module.id}>
                  <TableCell className="font-medium">{module.title}</TableCell>
                  <TableCell className="max-w-80 truncate text-sm text-muted-foreground">
                    {module.description || '—'}
                  </TableCell>
                  <TableCell>
                    <span className="flex items-center gap-2 text-sm">
                      <span className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary">
                        {module.userName.slice(0, 1).toUpperCase()}
                      </span>
                      {module.userName}
                    </span>
                  </TableCell>
                  <TableCell>
                    <Badge variant={module.isAudit === 1 ? 'secondary' : 'destructive'}>
                      {module.isAudit === 1 ? '已通过' : '待审核'}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right">
                    {module.isAudit === 1 ? (
                      <Button
                        size="sm"
                        variant="outline"
                        className="gap-1.5"
                        onClick={() => audit.mutate({ id: module.id, isAudit: 0 })}
                      >
                        <RotateCcw className="size-3.5" />
                        取消审核
                      </Button>
                    ) : (
                      <Button
                        size="sm"
                        className="gap-1.5"
                        onClick={() => audit.mutate({ id: module.id, isAudit: 1 })}
                      >
                        <Check className="size-3.5" />
                        通过
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))}
              {(query.data?.items ?? []).length === 0 && (
                <TableRow>
                  <TableCell colSpan={5}>
                    <EmptyState title="暂无待审核模块" description="用户提交公开模块后会出现在这里。" />
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>

          <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-3 text-sm">
            <span className="text-xs text-muted-foreground">
              第 {pno} / {pages} 页 · 共 {total} 条
            </span>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                className="gap-1"
                disabled={pno <= 1}
                onClick={() => setPno(pno - 1)}
              >
                <ChevronLeft className="size-3.5" />
                上一页
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="gap-1"
                disabled={pno >= pages}
                onClick={() => setPno(pno + 1)}
              >
                下一页
                <ChevronRight className="size-3.5" />
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
      </div>
    </AdminGuard>
  )
}
