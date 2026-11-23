import { test, expect } from '@playwright/test'

const base = process.env.INSTALL_BASE_URL

test.skip(!base, 'INSTALL_BASE_URL is not set')

test('installer wizard configures a fresh deployment', async ({ page }) => {
  await page.goto(`${base}/install`)
  await expect(page.getByText(/安装向导/)).toBeVisible()
  await page.getByRole('button', { name: /我同意此协议/ }).click()

  await page.getByLabel('数据库地址').fill('127.0.0.1:33061')
  await page.getByLabel('数据库名称').fill('xssplatform_ui_e2e')
  await page.getByLabel('数据库用户名').fill('root')
  await page.getByLabel('数据库密码').fill('test')
  await page.getByLabel('管理员', { exact: true }).fill('admin')
  await page.getByLabel('管理员密码', { exact: true }).fill('ui&pass123')

  await page.getByRole('button', { name: '测试连接' }).click()
  await expect(page.getByText('数据库连接成功')).toBeVisible()
  await page.getByRole('button', { name: '下一步' }).click()
  await page.getByRole('button', { name: '开始安装' }).click()
  await expect(page.getByText('导入数据成功')).toBeVisible()

  await page.goto(`${base}/login`)
  await page.getByLabel(/用户名或邮箱/i).fill('admin')
  await page.getByLabel(/^密码$/).fill('ui&pass123')
  await page.getByRole('button', { name: /^登录$/ }).click()
  await expect(page).toHaveURL(`${base}/`)
  await expect(page.getByRole('main').getByText('我的项目')).toBeVisible()

  await page.goto(`${base}/install`)
  await expect(page.getByText('已安装')).toBeVisible()
})
