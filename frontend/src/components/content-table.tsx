import { Fragment, useState } from 'react'
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
    <ul className="font-mono text-xs">
      {Object.entries(data).map(([key, value]) => (
        <li key={key} className="flex gap-2 border-b border-border/40 py-1 last:border-0">
          <span className="w-28 shrink-0 text-muted-foreground">{key}</span>
          <span className="break-all">{value}</span>
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
        <Table className="table-fixed">
          <TableHeader className="bg-muted/40">
            <TableRow>
              <TableHead className="w-10" />
              <TableHead className="w-20">详情</TableHead>
              <TableHead className="w-36">时间</TableHead>
              <TableHead className="w-[27%]">Domain</TableHead>
              <TableHead className="w-[18%]">Headers</TableHead>
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
                <Fragment key={item.id}>
                  <TableRow className="align-top">
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
                    <TableCell className="truncate text-muted-foreground">
                      {item.domain || '-'}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {Object.keys(item.serverContent ?? {}).length} 项
                    </TableCell>
                    <TableCell>
                      <ConfirmDelete onConfirm={() => onDelete(item.id)} />
                    </TableCell>
                  </TableRow>
                  {open && (
                    <TableRow className="bg-muted/30 hover:bg-muted/30">
                      <TableCell colSpan={6} className="whitespace-normal">
                        <div className="grid gap-6 px-2 py-2 md:grid-cols-2">
                          <section className="flex flex-col gap-1">
                            <h4 className="text-xs font-medium text-muted-foreground">接收的内容</h4>
                            <KeyList data={item.content} />
                          </section>
                          <section className="flex flex-col gap-1">
                            <h4 className="text-xs font-medium text-muted-foreground">Request Headers</h4>
                            <KeyList data={item.serverContent} />
                          </section>
                        </div>
                      </TableCell>
                    </TableRow>
                  )}
                </Fragment>
              )
            })}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}
