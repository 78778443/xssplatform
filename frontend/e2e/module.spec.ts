import { test, expect, type Page } from '@playwright/test'

async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel(/用户名或邮箱/i).fill('admin')
  await page.getByLabel(/^密码$/).fill('pw123456')
  await page.getByRole('button', { name: /^登录$/ }).click()
  await expect(page).toHaveURL(/\/$/)
}

test('create a module and read it back', async ({ page }) => {
  await login(page)
  await page.goto('/modules/new')
  await page.getByLabel(/模块名称/).fill('E2E 模块')
  await page.getByLabel(/代码/).fill('x=1')
  await page.getByRole('button', { name: /^创建$/ }).click()
  await expect(page).toHaveURL(/\/modules\/\d+$/)
  await expect(page.getByRole('heading', { name: 'E2E 模块', level: 1 })).toBeVisible()
})

test('admin overview and module audit pages render', async ({ page }) => {
  await login(page)
  await page.goto('/admin')
  await expect(page.getByText(/注册用户总数/)).toBeVisible()
  await page.goto('/admin/modules')
  await expect(page.getByText(/模块审核/).first()).toBeVisible()
})
