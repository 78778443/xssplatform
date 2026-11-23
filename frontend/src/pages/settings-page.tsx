import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
    <Card>
      <CardHeader>
        <CardTitle>个人设置</CardTitle>
      </CardHeader>
      <CardContent className="flex max-w-xl flex-col gap-6">
        <div className="text-sm">
          邮箱：<span className="text-muted-foreground">{query.data?.email ?? ''}</span>
        </div>

        <div className="flex items-center justify-between gap-4">
          <Label htmlFor="emsg">发送邮件提醒</Label>
          <Switch id="emsg" checked={emsg} onCheckedChange={setEmsg} />
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="dingding">钉钉机器人Key</Label>
          <Input id="dingding" value={dingding} onChange={(event) => setDingding(event.target.value)} />
        </div>

        <div className="flex items-center justify-between gap-4">
          <Label htmlFor="dmsg">发送钉钉通知</Label>
          <Switch id="dmsg" checked={dmsg} onCheckedChange={setDmsg} />
        </div>

        <Button onClick={() => save.mutate()} disabled={save.isPending}>
          提交
        </Button>
      </CardContent>
    </Card>
  )
}
