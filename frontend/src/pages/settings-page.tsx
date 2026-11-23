import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { PageHeader } from '@/components/layout/page-header'
import { BellRing, Mail } from 'lucide-react'
import { QueryError } from '@/components/query-error'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'

interface SettingsData {
  email: string
  dingding: string
  emsg: string
  dmsg: string
}

export function SettingsPage() {
  const { data: session } = useSession()
  const queryClient = useQueryClient()
  const query = useQuery({
    queryKey: ['settings'],
    queryFn: () => api<SettingsData>('user.settings.get'),
  })
  const [dingding, setDingding] = useState('')
  const [emsg, setEmsg] = useState(false)
  const [dmsg, setDmsg] = useState(false)

  useEffect(() => {
    if (!query.data) return
    setDingding(query.data.dingding)
    setEmsg(query.data.emsg === '1')
    setDmsg(query.data.dmsg === '1')
  }, [query.data])

  const save = useMutation({
    mutationFn: () =>
      api('user.settings.save', {
        method: 'POST',
        csrf: session?.csrf,
        body: {
          emsg: emsg ? '1' : '0',
          dmsg: dmsg ? '1' : '0',
          dingding,
        },
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['settings'] })
      toast.success('修改成功')
    },
    onError: (error) => toast.error(error instanceof Error ? error.message : '保存失败'),
  })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        eyebrow="Account"
        title="个人设置"
        description="配置收包提醒方式，邮件与钉钉通知会作用于你的所有项目。"
      />

      {query.isError && <QueryError error={query.error} />}

      <Card>
        <CardHeader className="flex flex-row items-center gap-3">
          <span className="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary/20 to-primary/5 text-primary ring-1 ring-primary/15">
            <BellRing className="size-5" />
          </span>
          <div className="flex flex-col gap-1">
            <CardTitle>通知设置</CardTitle>
            <CardDescription>收到新的收包内容时，通过邮件或钉钉提醒你。</CardDescription>
          </div>
        </CardHeader>
        <CardContent className="flex max-w-2xl flex-col gap-5">
          <div className="flex items-center gap-3 rounded-xl border border-border/70 bg-muted/30 px-3 py-2.5 text-sm">
            <Mail className="size-4 text-muted-foreground" />
            <span className="text-muted-foreground">账号邮箱</span>
            <span className="ml-auto font-medium">{query.data?.email ?? ''}</span>
          </div>

          <div className="flex items-center justify-between gap-4 rounded-xl border border-border/70 px-3 py-3">
            <div className="flex flex-col gap-0.5">
              <Label htmlFor="emsg">发送邮件提醒</Label>
              <span className="text-xs text-muted-foreground">新内容到达时发送邮件</span>
            </div>
            <Switch id="emsg" checked={emsg} onCheckedChange={setEmsg} />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="dingding">钉钉机器人Key</Label>
            <Input
              id="dingding"
              className="h-10"
              value={dingding}
              onChange={(event) => setDingding(event.target.value)}
              placeholder="填写钉钉机器人 Webhook 的 access_token"
            />
          </div>

          <div className="flex items-center justify-between gap-4 rounded-xl border border-border/70 px-3 py-3">
            <div className="flex flex-col gap-0.5">
              <Label htmlFor="dmsg">发送钉钉通知</Label>
              <span className="text-xs text-muted-foreground">新内容到达时推送到钉钉群</span>
            </div>
            <Switch id="dmsg" checked={dmsg} onCheckedChange={setDmsg} />
          </div>

          <Button onClick={() => save.mutate()} disabled={save.isPending} className="self-start">
            提交
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}
