import { useState } from 'react'
import { ChevronDown, ChevronUp } from 'lucide-react'
import type { ContentItem } from '@/lib/types'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
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

function formatTime(value: number) {
  if (!value) return ''
  const date = new Date(value * 1000)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

function KeyList({ data }: { data: Record<string, string> }) {
  return (
    <ul className="font-mono text-xs break-all">
      {Object.entries(data).map(([key, value]) => (
        <li key={key}>
          {key} : {value}
        </li>
      ))}
    </ul>
  )
}

export function ContentTable({
  contents,
  token: _token,
  onDelete,
  onDeleteMany,
}: {
  contents: ContentItem[]
  token: string
  onDelete: (id: number) => void
  onDeleteMany: (ids: number[]) => void
}) {
  const [expanded, setExpanded] = useState<Record<number, boolean>>({})
  const [selected, setSelected] = useState<Record<number, boolean>>({})

  const selectedIds = Object.keys(selected).filter((id) => selected[Number(id)]).map(Number)
  const allChecked = contents.length > 0 && contents.every((item) => selected[item.id])

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center justify-between text-sm">
        <label className="flex items-center gap-2">
          <Checkbox
            checked={allChecked}
            onCheckedChange={(checked) => {
              const next: Record<number, boolean> = {}
              if (checked === true) {
                for (const item of contents) next[item.id] = true
              }
              setSelected(next)
            }}
          />
          全选
        </label>
        <Button
          variant="outline"
          size="sm"
          disabled={selectedIds.length === 0}
          onClick={() => {
            onDeleteMany(selectedIds)
            setSelected({})
          }}
        >
          删除选中项
        </Button>
      </div>

      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10" />
              <TableHead className="w-16">详情</TableHead>
              <TableHead className="w-40">时间</TableHead>
              <TableHead>接收的内容</TableHead>
              <TableHead>Request Headers</TableHead>
              <TableHead className="w-16">操作</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {contents.length === 0 && (
              <TableRow>
                <TableCell colSpan={6}>
                  <EmptyState title="暂无内容" description="把项目代码植入目标页面后会在这里显示" />
                </TableCell>
              </TableRow>
            )}
            {contents.map((item) => {
              const open = Boolean(expanded[item.id])
              return (
                <TableRow key={item.id} className="align-top">
                  <TableCell>
                    <Checkbox
                      checked={Boolean(selected[item.id])}
                      onCheckedChange={(checked) =>
                        setSelected((prev) => ({ ...prev, [item.id]: checked === true }))
                      }
                    />
                  </TableCell>
                  <TableCell>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="px-1"
                      onClick={() => setExpanded((prev) => ({ ...prev, [item.id]: !open }))}
                    >
                      {open ? <ChevronUp /> : <ChevronDown />}
                      {open ? '折叠' : '展开'}
                    </Button>
                  </TableCell>
                  <TableCell className="whitespace-nowrap">{formatTime(item.addTime)}</TableCell>
                  <TableCell>
                    {open ? <KeyList data={item.content} /> : <span className="text-muted-foreground">{item.domain || '-'}</span>}
                  </TableCell>
                  <TableCell>{open ? <KeyList data={item.serverContent} /> : <span className="text-muted-foreground">-</span>}</TableCell>
                  <TableCell>
                    <ConfirmDelete onConfirm={() => onDelete(item.id)} />
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}
