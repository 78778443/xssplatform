import { test, expect, type Page } from '@playwright/test'
import { execSync } from 'node:child_process'
import { mkdirSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const outDir = fileURLToPath(new URL('../../docs/screenshots', import.meta.url))

async function login(page: Page) {
  await page.goto('/login')
  await page.getByLabel(/用户名或邮箱/i).fill('admin')
  await page.getByLabel(/^密码$/).fill('pw123456')
  await page.getByRole('button', { name: /^登录$/ }).click()
  await expect(page).toHaveURL(/\/$/)
}

async function shot(page: Page, name: string, waitFor?: string) {
  if (waitFor) {
    await expect(page.getByText(waitFor).first()).toBeVisible()
  }
  await page.mouse.move(0, 0)
  await page.waitForTimeout(400)
  await page.screenshot({ path: `${outDir}/${name}.png`, fullPage: false })
}

test('capture README screenshots', async ({ page }) => {
  mkdirSync(outDir, { recursive: true })
  execSync(`bash ${fileURLToPath(new URL('../../tests/http/screenshot-reset.sh', import.meta.url))}`, {
    stdio: 'pipe',
  })
  try {

    await page.goto('/login')
    await shot(page, 'login')

    await login(page)
    await shot(page, 'dashboard', '我的项目')

    await page.goto('/project/194')
    await page.getByText(/展开/).first().click()
    await shot(page, 'project', '后台管理系统')

    await page.goto('/project/194/code')
    await shot(page, 'code', '项目代码')

    await page.goto('/modules')
    await shot(page, 'modules', '公共模块')

    await page.goto('/admin')
    await shot(page, 'admin', '注册用户总数')

    await page.goto('/admin/modules')
    await shot(page, 'admin-modules', '模块审核')
  } finally {
    execSync(`bash ${fileURLToPath(new URL('../../tests/http/screenshot-restore.sh', import.meta.url))}`, {
      stdio: 'pipe',
    })
  }

})
