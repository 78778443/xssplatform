import { test, expect, type Page } from '@playwright/test'

async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel(/用户名或邮箱/i).fill('admin')
  await page.getByLabel(/^密码$/).fill('pw123456')
  await page.getByRole('button', { name: /^登录$/ }).click()
  await expect(page).toHaveURL(/\/$/)
}

test('create a project, configure it and view the code', async ({ page }) => {
  await login(page)
  await page.goto('/project/new')
  await page.getByLabel(/项目名称/).fill('E2E 项目')
  await page.getByLabel(/项目描述/).fill('来自 Playwright 的自动化创建')
  await page.getByRole('button', { name: /^创建$/ }).click()
  await expect(page).toHaveURL(/\/project\/\d+\/config/)
  await page.getByRole('button', { name: /下一步/ }).click()
  await expect(page).toHaveURL(/\/project\/\d+\/code/)
  await expect(page.getByText(/项目代码/).first()).toBeVisible()
})

test('project content table expands a received row', async ({ page }) => {
  await login(page)
  await page.goto('/project/194')
  await expect(page.getByText(/后台管理系统/).first()).toBeVisible()
  const row = page.locator('tr', { hasText: 'admin.example.com' }).first()
  await row.getByRole('button', { name: /展开/ }).click()
  await expect(page.getByText(/SESSION=/).first()).toBeVisible()
})
