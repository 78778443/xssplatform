import { test, expect } from '@playwright/test'

async function login(page: import('@playwright/test').Page) {
  await page.goto('/login')
  await page.getByLabel(/用户名或邮箱/i).fill('admin')
  await page.getByLabel(/^密码$/).fill('pw123456')
  await page.getByRole('button', { name: /^登录$/ }).click()
  await expect(page).toHaveURL(/\/$/)
}

test('login, dashboard and logout', async ({ page }) => {
  await login(page)
  await expect(page.getByText(/我的项目/i).first()).toBeVisible()
  await page.getByRole('button', { name: /退出登陆/i }).click()
  await expect(page).toHaveURL(/\/login/)
})

test('register a new account and land on the dashboard', async ({ page }) => {
  const suffix = String(Date.now()).slice(-6)
  await page.goto('/register')
  await page.getByLabel(/用户名/).fill(`e2e${suffix}`)
  await page.getByLabel(/邮箱/).fill(`e2e${suffix}@example.com`)
  await page.getByLabel(/^密码$/).fill('123456')
  await page.getByLabel(/确认密码/).fill('123456')
  await page.getByRole('button', { name: /^注册$/ }).click()
  await expect(page).toHaveURL(/\/$/)
})
