import { test, expect, type Page } from '@playwright/test'

async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel(/用户名或邮箱/i).fill('admin')
  await page.getByLabel(/^密码$/).fill('pw123456')
  await page.getByRole('button', { name: /^登录$/ }).click()
  await expect(page).toHaveURL(/\/$/)
}

async function csrf(page: Page): Promise<string> {
  const response = await page.request.get('/api.php?action=session')
  const body = await response.json()
  return body.data.csrf as string
}

test('settings and invites persist through the UI', async ({ page }) => {
  await login(page)
  const token = await csrf(page)

  await page.goto('/settings')
  await page.getByLabel(/飞书机器人 Webhook/).fill('https://open.feishu.cn/open-apis/bot/v2/hook/e2e-key')
  const switches = page.getByRole('switch')
  if ((await switches.nth(0).getAttribute('aria-checked')) !== 'true') await switches.nth(0).click()
  await page.getByRole('button', { name: /^提交$/ }).click()
  await expect(page.getByText('修改成功')).toBeVisible()
  await page.reload()
  await expect(page.getByLabel(/飞书机器人 Webhook/)).toHaveValue('https://open.feishu.cn/open-apis/bot/v2/hook/e2e-key')

  const before = await page.request.get('/api.php?action=user.invites')
  const beforeCount = ((await before.json()).data.other as unknown[]).length

  await page.goto('/invites')
  await page.getByRole('button', { name: /生成邀请码/ }).last().click()
  await expect(page.getByText('操作成功')).toBeVisible()
  const after = await page.request.get('/api.php?action=user.invites')
  expect(((await after.json()).data.other as unknown[]).length).toBe(beforeCount + 1)
  expect(token.length).toBe(32)
})

test('module edit and delete work from the UI', async ({ page }) => {
  await login(page)

  await page.goto('/modules/new')
  await page.getByLabel(/模块名称/).fill('E2E 待改名')
  await page.getByLabel(/代码/).fill('x=1')
  await page.getByRole('button', { name: /^创建$/ }).click()
  await expect(page).toHaveURL(/\/modules\/\d+$/)
  const moduleId = Number(page.url().match(/modules\/(\d+)/)?.[1])
  expect(moduleId).toBeGreaterThan(0)

  await page.goto(`/modules/${moduleId}/edit`)
  await page.getByLabel(/模块名称/).fill('E2E 已改名')
  await page.getByRole('button', { name: /^配置$/ }).click()
  await expect(page).toHaveURL(`/modules/${moduleId}`)
  await expect(page.getByLabel(/模块名称/)).toHaveValue('E2E 已改名')

  await page.goto(`/modules/${moduleId}/edit`)
  await page.getByRole('button', { name: /删除模块/ }).click()
  await page.getByRole('button', { name: /^确定$/ }).click()
  await expect(page).toHaveURL(/\/modules$/)
})

test('content can be filtered by domain and deleted', async ({ page }) => {
  await login(page)
  const token = await csrf(page)
  const title = `E2E 收包 ${Date.now()}`
  const created = await page.request.post('/api.php?action=project.create', {
    headers: { 'X-CSRF-Token': token },
    data: { title, description: '' },
  })
  const project = (await created.json()).data as { id: number; urlKey: string }

  for (const [domain, cookie] of [['alpha.example', 'a=1'], ['beta.example', 'b=2']]) {
    await page.request.get(
      `/index.php?do=api&id=${project.urlKey}&cookie=${cookie}&location=http://${domain}/`,
      { headers: { Referer: `http://${domain}/` }, maxRedirects: 0 },
    )
  }

  await page.goto(`/project/${project.id}`)
  await expect(page.getByRole('row', { name: /alpha\.example/ })).toBeVisible()
  await expect(page.getByRole('row', { name: /beta\.example/ })).toBeVisible()

  await page.getByLabel('Domain').selectOption('beta.example')
  await expect(page.getByRole('row', { name: /beta\.example/ })).toBeVisible()
  await expect(page.getByRole('row', { name: /alpha\.example/ })).toHaveCount(0)

  await page.getByRole('button', { name: /^删除$/ }).first().click()
  await page.getByRole('button', { name: /^确定$/ }).click()
  await expect(page.getByRole('row', { name: /beta\.example/ })).toHaveCount(0)

  await page.goto('/')
  const projectRow = page.getByRole('row', { name: new RegExp(title) }).first()
  await projectRow.getByRole('button', { name: '删除' }).click()
  await page.getByRole('button', { name: /^确定$/ }).click()
  await expect(page.getByText(title)).toHaveCount(0)
})

test('admin can audit a pending module', async ({ page }) => {
  await login(page)
  const token = await csrf(page)
  const name = `E2E 待审核 ${Date.now()}`
  const created = await page.request.post('/api.php?action=module.create', {
    headers: { 'X-CSRF-Token': token },
    data: { title: name, description: '', keys: ['cookie'], setkeys: [], code: 'x=1', isOpen: 1 },
  })
  const moduleId = (await created.json()).data.id as number

  await page.goto('/admin/modules')
  const row = page.getByRole('row', { name: new RegExp(name) })
  await expect(row).toContainText('待审核')
  await row.getByRole('button', { name: /^通过$/ }).click()
  await expect(page.getByText('操作成功')).toBeVisible()
  await expect(page.getByRole('row', { name: new RegExp(name) })).toContainText('已通过')

  const modules = await page.request.get('/api.php?action=modules.list')
  expect(await modules.text()).toContain(name)
  expect(moduleId).toBeGreaterThan(0)
})

test('unauthorized visitors land on login and unknown routes show 404', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveURL(/\/login$/)
  await expect(page.getByRole('button', { name: /^登录$/ })).toBeVisible()

  await login(page)
  await page.goto('/nope')
  await expect(page.getByText('页面不存在')).toBeVisible()
})

test('mobile shell exposes navigation through the menu sheet', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await login(page)
  await expect(page.getByRole('navigation')).toHaveCount(0)
  await page.getByRole('button', { name: '打开菜单' }).click()
  await expect(page.getByRole('dialog').getByRole('link', { name: '模块库' })).toBeVisible()
  await page.getByRole('dialog').getByRole('link', { name: '模块库' }).click()
  await expect(page).toHaveURL(/\/modules$/)
})

test('theme toggle switches to dark and persists', async ({ page }) => {
  await login(page)
  await page.getByRole('button', { name: '切换主题' }).click()
  await expect(page.locator('html')).toHaveClass(/dark/)
  await page.reload()
  await expect(page.locator('html')).toHaveClass(/dark/)
})

test('an already logged-in visitor is redirected away from /login', async ({ page }) => {
  await login(page)
  await page.goto('/login')
  await expect(page).toHaveURL(/\/$/)
})
