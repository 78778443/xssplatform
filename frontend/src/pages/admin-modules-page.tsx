import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import { AdminGuard } from '@/components/admin-guard'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
      <Card>
        <CardHeader>
          <CardTitle>模块审核</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>模块名称</TableHead>
                <TableHead>描述</TableHead>
                <TableHead>作者</TableHead>
                <TableHead className="w-28">状态</TableHead>
                <TableHead className="w-32">操作</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(query.data?.items ?? []).map((module) => (
                <TableRow key={module.id}>
                  <TableCell>{module.title}</TableCell>
                  <TableCell className="max-w-80 truncate">{module.description}</TableCell>
                  <TableCell>{module.userName}</TableCell>
                  <TableCell>{module.isAudit === 1 ? '已通过' : '待审核'}</TableCell>
                  <TableCell>
                    {module.isAudit === 1 ? (
                      <Button size="sm" variant="outline" onClick={() => audit.mutate({ id: module.id, isAudit: 0 })}>
                        取消审核
                      </Button>
                    ) : (
                      <Button size="sm" onClick={() => audit.mutate({ id: module.id, isAudit: 1 })}>
                        通过
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))}
              {(query.data?.items ?? []).length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                    暂无模块
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>

          <div className="flex items-center justify-end gap-2 text-sm">
            <span className="text-muted-foreground">
              第 {pno} / {pages} 页，共 {total} 条
            </span>
            <Button variant="outline" size="sm" disabled={pno <= 1} onClick={() => setPno(pno - 1)}>
              上一页
            </Button>
            <Button variant="outline" size="sm" disabled={pno >= pages} onClick={() => setPno(pno + 1)}>
              下一页
            </Button>
          </div>
        </CardContent>
      </Card>
    </AdminGuard>
  )
}
