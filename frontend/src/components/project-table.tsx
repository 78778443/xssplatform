import { Link } from 'react-router-dom'
import { FolderKanban } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import type { ProjectSummary } from '@/lib/types'
import { ConfirmDelete } from '@/components/confirm-delete'
import { EmptyState } from '@/components/empty-state'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

function formatDate(value: number) {
  if (!value) return ''
  const date = new Date(value * 1000)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export function ProjectTable({
  projects,
  onDelete,
}: {
  projects: ProjectSummary[]
  onDelete: (id: number) => void
}) {
  return (
    <div className="overflow-x-auto">
      <Table className="table-fixed">
        <TableHeader className="bg-muted/40">
          <TableRow>
            <TableHead className="w-[22%]">项目名称</TableHead>
            <TableHead className="w-[42%]">项目描述</TableHead>
            <TableHead className="w-24">内容数</TableHead>
            <TableHead className="w-28">创建时间</TableHead>
            <TableHead className="w-16">操作</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {projects.length === 0 && (
            <TableRow>
              <TableCell colSpan={5}>
                <EmptyState title="还没有项目" description="点击右上角创建第一个项目" />
              </TableCell>
            </TableRow>
          )}
          {projects.map((project) => (
            <TableRow key={project.id}>
              <TableCell>
                <div className="flex items-center gap-2.5">
                  <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/15">
                    <FolderKanban className="size-4" />
                  </span>
                  <Link
                    to={`/project/${project.id}`}
                    className="truncate font-medium text-foreground hover:text-primary hover:underline"
                  >
                    {project.title}
                  </Link>
                </div>
              </TableCell>
              <TableCell className="truncate text-muted-foreground">{project.description}</TableCell>
              <TableCell>
                <Badge variant="secondary">{project.contentNum}</Badge>
              </TableCell>
              <TableCell className="text-xs text-muted-foreground tabular-nums">{formatDate(project.addTime)}</TableCell>
              <TableCell>
                <ConfirmDelete onConfirm={() => onDelete(project.id)} />
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
