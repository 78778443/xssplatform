import { Link } from 'react-router-dom'
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
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-48">项目名称</TableHead>
            <TableHead>项目描述</TableHead>
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
                <Link to={`/project/${project.id}`}>{project.title}</Link>
              </TableCell>
              <TableCell className="max-w-80 truncate">{project.description}</TableCell>
              <TableCell>{project.contentNum}</TableCell>
              <TableCell>{formatDate(project.addTime)}</TableCell>
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
