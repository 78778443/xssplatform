import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { useSession } from '@/lib/session'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { PageHeader } from '@/components/layout/page-header'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

export function ProjectNewPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: session } = useSession()
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [error, setError] = useState('')
  const [pending, setPending] = useState(false)

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setError('')
    if (title.trim() === '') {
      setError('项目名称不能为空')
      return
    }
    setPending(true)
    try {
      const result = await api<{ id: number }>('project.create', {
        method: 'POST',
        csrf: session?.csrf,
        body: { title, description },
      })
      await queryClient.invalidateQueries({ queryKey: ['dashboard'] })
      navigate(`/project/${result.id}/config?created=1`)
    } catch (err) {
      setError(err instanceof Error ? err.message : '创建失败')
    } finally {
      setPending(false)
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        eyebrow="Project"
        title="创建项目"
        description="为一次授权验证建立独立的收包项目，随后选择模块并生成代码。"
      />

      <Card className="max-w-2xl">
        <CardContent>
          <form className="flex flex-col gap-5" onSubmit={submit}>
            <div className="flex flex-col gap-2">
              <Label htmlFor="project-title">项目名称</Label>
              <Input
                id="project-title"
                className="h-10"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="例如：后台管理系统验证"
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="project-description">项目描述</Label>
              <Textarea
                id="project-description"
                rows={4}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="记录目标、验证范围或授权信息"
              />
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            <div className="-mx-4 -mb-4 mt-1 flex items-center gap-2.5 rounded-b-2xl border-t border-border/70 bg-muted/20 px-4 py-3.5">
              <Button type="submit" disabled={pending}>
                创建
              </Button>
              <Link to="/" className={buttonVariants({ variant: 'outline' })}>
                取消
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
